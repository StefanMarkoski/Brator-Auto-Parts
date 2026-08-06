<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Services;

use App\Domain\Ordering\Models\Coupon;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\Session;

/**
 * The coupon the shopper has applied, remembered across pages.
 *
 * Held in the SESSION rather than on the basket row, for the same reason the chosen vehicle
 * is: it belongs to this browsing session, not to the goods. It also means a code cannot
 * outlive the visit and surprise somebody later.
 *
 * The CODE is stored, never the discount. Storing the amount would freeze a figure that
 * depends on the basket, and the basket changes — remove a line and a stored discount would
 * still be taking money off a total that no longer justifies it.
 */
final class AppliedCoupon
{
    public const SESSION_KEY = 'applied_coupon_code';

    public function code(): ?string
    {
        $value = Session::get(self::SESSION_KEY);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The coupon, if one is applied AND still usable.
     *
     * Re-read every time rather than cached: a code deactivated by staff while it sits in
     * somebody's session must stop discounting from that moment, not at checkout.
     */
    public function coupon(): ?Coupon
    {
        $code = $this->code();

        return $code === null ? null : Coupon::findUsable($code);
    }

    public function apply(Coupon $coupon): void
    {
        Session::put(self::SESSION_KEY, $coupon->code);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * The coupon that actually earns its discount on this basket, or null.
     *
     * A code below its minimum stays APPLIED but discounts nothing, so the cart can say
     * "spend 400 more and this saves you 250" rather than silently dropping it. Forgetting a
     * code the moment a line is removed would be the more annoying behaviour.
     */
    public function effectiveFor(Money $netSubtotal): ?Coupon
    {
        $coupon = $this->coupon();

        return $coupon !== null && $coupon->appliesTo($netSubtotal) ? $coupon : null;
    }
}
