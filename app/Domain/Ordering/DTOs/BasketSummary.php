<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTOs;

use App\Domain\Ordering\Models\Coupon;
use App\Domain\Ordering\Support\DeliveryCharge;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * What the cart page and the checkout both need: the lines, and the arithmetic.
 *
 * VAT is computed PER LINE and then summed — never on the subtotal. The two give
 * different answers and the gap is impossible to explain on an invoice.
 */
final readonly class BasketSummary
{
    /** @param  Collection<int, BasketLineSummary>  $lines */
    private function __construct(
        public Collection $lines,
        public Money $subtotal,
        public Money $discount,
        public Money $vat,
        public Money $shipping,
        public Money $total,
        public int $itemCount,
        public ?Coupon $coupon = null,
    ) {}

    /** @param  Collection<int, BasketLineSummary>  $lines */
    public static function fromLines(Collection $lines, float $vatRate, ?Coupon $coupon = null): self
    {
        $subtotal = $lines->reduce(
            fn (Money $carry, BasketLineSummary $line): Money => $carry->add($line->lineTotal),
            Money::zero()
        );

        /*
         | THE DISCOUNT COMES OFF THE NET SUBTOTAL, AND VAT IS CHARGED ON WHAT REMAINS.
         |
         | That is the correct treatment and not merely the convenient one: a discount reduces
         | the consideration, so it reduces the taxable base. Taking the discount off the
         | gross total instead would have the shop paying VAT on money it never received.
        */
        $discount = $coupon?->discountOn($subtotal) ?? Money::zero();
        $discounted = $subtotal->subtract($discount);

        $vat = $lines->reduce(
            fn (Money $carry, BasketLineSummary $line): Money => $carry->add($line->lineTotal->vatAt($vatRate)),
            Money::zero()
        );

        if (! $discount->isZero() && ! $subtotal->isZero()) {
            /*
             | VAT recomputed on the discounted base rather than scaled per line.
             |
             | Per-line VAT is summed above because that is what an invoice has to show, but a
             | basket-level percentage discount cannot be attributed to lines without
             | rounding drift. Recomputing on the discounted subtotal keeps the VAT figure
             | consistent with the amount actually charged, which is the number that has to
             | reconcile.
            */
            $vat = $discounted->vatAt($vatRate);
        }

        // One source of truth for delivery, shared with PlaceReceiptAction so the
        // cart and the receipt cannot disagree about what someone owes.
        //
        // Measured on the DISCOUNTED subtotal: "free over 3.000" means 3.000 actually spent.
        // A basket of 3.100 with 10% off is 2.790 spent, so delivery is charged.
        $shipping = DeliveryCharge::for($discounted);

        // Delivery carries VAT too. Leaving it out zero-rated the charge and
        // under-collected on every order that paid for delivery.
        $vat = $vat->add(DeliveryCharge::vatOn($shipping, $vatRate));

        return new self(
            lines: $lines,
            subtotal: $subtotal,
            discount: $discount,
            vat: $vat,
            shipping: $shipping,
            total: $discounted->add($vat)->add($shipping),
            itemCount: (int) $lines->sum(fn (BasketLineSummary $line) => $line->quantity),
            coupon: $coupon,
        );
    }

    public static function empty(): self
    {
        return self::fromLines(collect(), 0.0);
    }

    /** The net subtotal after any discount — what the VAT and delivery rules both act on. */
    public function discountedSubtotal(): Money
    {
        return $this->subtotal->subtract($this->discount);
    }

    public function hasDiscount(): bool
    {
        return ! $this->discount->isZero();
    }

    /**
     * The amount the VAT figure was actually charged on.
     *
     * Exists because the summaries kept naming a base the number was not computed on. The
     * cart printed "On 900,00 after discount" beside a VAT of 196,20 — which is 162,00 on
     * the goods PLUS 34,20 on delivery, so the sentence was describing two thirds of its
     * own figure. Delivery is a taxable supply here (see DeliveryCharge::vatOn), so the
     * base is the discounted goods and the delivery charge together, and it drops back to
     * the goods alone if an accountant ever flips shop.vat_on_delivery.
     */
    public function vatBase(): Money
    {
        return config('shop.vat_on_delivery', true)
            ? $this->discountedSubtotal()->add($this->shipping)
            : $this->discountedSubtotal();
    }

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    public static function freeDeliveryFrom(): Money
    {
        return Money::fromMinor(DeliveryCharge::FREE_FROM_MINOR);
    }

    public function qualifiesForFreeShipping(): bool
    {
        return $this->shipping->isZero() && ! $this->isEmpty();
    }
}
