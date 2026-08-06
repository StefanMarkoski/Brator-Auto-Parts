<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Support\Casts\MoneyCast;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A percentage-off code.
 *
 * The model owns what a coupon MEANS — whether it applies to a given basket, and what it
 * takes off — so the cart, the checkout and the admin all get the same answer. The previous
 * generation of this codebase had the delivery rule copied into three places and they
 * disagreed about what somebody owed; a discount is the same shape of risk.
 */
class Coupon extends Model
{
    use HasUlids;

    protected $fillable = [
        'code', 'discount_percent', 'minimum_order_minor', 'is_active',
        'show_as_promotion', 'times_used',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'minimum_order_minor' => MoneyCast::class,
        'is_active' => 'boolean',
        'show_as_promotion' => 'boolean',
        'times_used' => 'integer',
    ];

    /** @param  Builder<Coupon>  $query */
    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The code advertised in the storefront's top bar, if any.
     *
     * ONE, not all of them. That bar is a single line of the purchased theme's markup, and
     * cramming three offers into it would need new layout. The newest promoted code wins,
     * because that is how a promo bar is actually managed — you tick the current campaign and
     * it takes over. The admin list says which one is showing so the choice is never a
     * mystery.
     *
     * Requires is_active too: advertising a code that has been switched off is the same class
     * of lie as the theme's "use code Brator50" for a code that never existed.
     */
    public static function promoted(): ?self
    {
        return self::query()
            ->where('show_as_promotion', true)
            ->where('is_active', true)
            ->latest('created_at')
            // A tiebreaker, because two codes made in the same second would otherwise swap
            // places between requests and the bar would flicker between offers.
            ->orderByDesc('id')
            ->first();
    }

    /** Codes are stored and compared uppercase, so what a customer types always matches. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public static function findUsable(string $code): ?self
    {
        $code = strtoupper(trim($code));

        return $code === '' ? null : self::query()->usable()->where('code', $code)->first();
    }

    /** Is there a minimum spend on this coupon at all? */
    public function hasMinimum(): bool
    {
        return $this->minimum_order_minor !== null && ! $this->minimum_order_minor->isZero();
    }

    /**
     * Does this coupon apply to a basket of this size?
     *
     * Measured against the NET subtotal — the same figure the cart shows as "Subtotal
     * (excluding VAT)". Testing it against the VAT-inclusive total would mean a "over 3.000"
     * coupon quietly triggering at 2.542 of actual goods, which is not what was advertised.
     */
    public function appliesTo(Money $netSubtotal): bool
    {
        if (! $this->is_active || $netSubtotal->isZero()) {
            return false;
        }

        return ! $this->hasMinimum()
            || $netSubtotal->toPrimitive() >= $this->minimum_order_minor->toPrimitive();
    }

    /**
     * What this coupon takes off a basket, or zero if it does not apply.
     *
     * Rounded half-up on the whole subtotal rather than per line. Per-line rounding on a
     * percentage drifts against the figure the shopper was shown, and the shown figure is
     * the one they agreed to.
     */
    public function discountOn(Money $netSubtotal): Money
    {
        if (! $this->appliesTo($netSubtotal)) {
            return Money::zero();
        }

        $discount = (int) round($netSubtotal->toPrimitive() * $this->discount_percent / 100);

        // Never more than the goods are worth, whatever the percentage says.
        return Money::fromMinor(min($discount, $netSubtotal->toPrimitive()));
    }

    /** Why this coupon cannot be used on this basket, in words a shopper can act on. */
    public function reasonItCannotApply(Money $netSubtotal): ?string
    {
        if (! $this->is_active) {
            return 'That code is no longer active.';
        }

        if ($netSubtotal->isZero()) {
            return 'Add something to your basket first.';
        }

        if ($this->hasMinimum() && $netSubtotal->toPrimitive() < $this->minimum_order_minor->toPrimitive()) {
            $short = Money::fromMinor(
                $this->minimum_order_minor->toPrimitive() - $netSubtotal->toPrimitive()
            );

            return "{$this->code} applies to orders over {$this->minimum_order_minor->format()}"
                ." (excluding VAT). You are {$short->format()} short.";
        }

        return null;
    }

    public function describe(): string
    {
        return $this->hasMinimum()
            ? "{$this->discount_percent}% off orders over {$this->minimum_order_minor->format()}"
            : "{$this->discount_percent}% off any order";
    }

    /**
     * The offer as the top bar says it: a headline, the condition, and the code.
     *
     * Returned as parts rather than one string because the theme's bar wraps each piece in its
     * own span, and the middle one is what its CSS highlights. Building the sentence here
     * keeps the wording in one place instead of spread across the markup.
     *
     * @return array{headline: string, condition: string, code: string}
     */
    public function promotionParts(): array
    {
        return [
            'headline' => "{$this->discount_percent}% OFF",
            'condition' => $this->hasMinimum()
                ? 'on orders over '.$this->minimum_order_minor->format().' with code'
                : 'on any order with code',
            'code' => $this->code,
        ];
    }
}
