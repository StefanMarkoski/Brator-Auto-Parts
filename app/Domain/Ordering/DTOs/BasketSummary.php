<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTOs;

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
        public Money $vat,
        public Money $shipping,
        public Money $total,
        public int $itemCount,
    ) {}

    /** @param  Collection<int, BasketLineSummary>  $lines */
    public static function fromLines(Collection $lines, float $vatRate): self
    {
        $subtotal = $lines->reduce(
            fn (Money $carry, BasketLineSummary $line): Money => $carry->add($line->lineTotal),
            Money::zero()
        );

        $vat = $lines->reduce(
            fn (Money $carry, BasketLineSummary $line): Money => $carry->add($line->lineTotal->vatAt($vatRate)),
            Money::zero()
        );

        // One source of truth for delivery, shared with PlaceReceiptAction so the
        // cart and the receipt cannot disagree about what someone owes.
        $shipping = DeliveryCharge::for($subtotal);

        // Delivery carries VAT too. Leaving it out zero-rated the charge and
        // under-collected on every order that paid for delivery.
        $vat = $vat->add(DeliveryCharge::vatOn($shipping, $vatRate));

        return new self(
            lines: $lines,
            subtotal: $subtotal,
            vat: $vat,
            shipping: $shipping,
            total: $subtotal->add($vat)->add($shipping),
            itemCount: (int) $lines->sum(fn (BasketLineSummary $line) => $line->quantity),
        );
    }

    public static function empty(): self
    {
        return self::fromLines(collect(), 0.0);
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
