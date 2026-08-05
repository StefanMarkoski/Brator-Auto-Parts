<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Support;

use App\Support\ValueObjects\Money;

/**
 * What delivery costs, in ONE place.
 *
 * The threshold and flat rate used to live as constants in BasketSummary and again as
 * inlined literals in PlaceReceiptAction. Two copies of a pricing rule means the cart
 * and the receipt can disagree about what someone owes — the reviewer flagged it before
 * they had a chance to.
 */
final class DeliveryCharge
{
    /** Free delivery from this net subtotal upward (3.000 ден). */
    public const FREE_FROM_MINOR = 300_000;

    public const FLAT_MINOR = 19_000;

    public static function for(Money $netSubtotal): Money
    {
        if ($netSubtotal->isZero() || $netSubtotal->minor >= self::FREE_FROM_MINOR) {
            return Money::zero();
        }

        return Money::fromMinor(self::FLAT_MINOR);
    }

    /**
     * VAT on the delivery charge.
     *
     * Delivery is a taxable supply in North Macedonia, so it carries VAT like any other
     * line. The first version added shipping AFTER the VAT sum, which zero-rated it and
     * under-collected about 34 ден on every order that paid for delivery — small per
     * order, systematic across all of them.
     *
     * Behind a config flag because it is a tax treatment, not a technical detail: if the
     * accountant says otherwise, this is the one line to change.
     */
    public static function vatOn(Money $delivery, float $ratePercent): Money
    {
        if (! config('shop.vat_on_delivery', true)) {
            return Money::zero();
        }

        return $delivery->vatAt($ratePercent);
    }
}
