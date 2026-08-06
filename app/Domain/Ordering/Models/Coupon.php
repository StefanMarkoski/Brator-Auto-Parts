<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Support\Casts\MoneyCast;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        'code', 'discount_percent', 'minimum_order_minor', 'is_active', 'times_used',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'minimum_order_minor' => MoneyCast::class,
        'is_active' => 'boolean',
        'times_used' => 'integer',
    ];

    /** @param  Builder<Coupon>  $query */
    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Every code advertised in the storefront's top bar, newest first.
     *
     * All of the live ones, each on its own line. There is no separate "advertise" flag: a
     * code that is switched on is a code the shop wants used, and a code it wants used is one
     * it wants seen. Switching off is therefore the only control, which also means a code can
     * never be advertised after it has stopped working — the exact lie the theme shipped with
     * its "use code Brator50" for a code that never existed.
     *
     * Deliberately uncapped. The bar grows a line per live code, so how tall it gets is a
     * consequence of how many codes are switched on, and that is staff's call rather than a
     * limit hidden in here.
     *
     * @return Collection<int, Coupon>
     */
    public static function advertised(): Collection
    {
        return self::query()
            ->usable()
            ->latest('created_at')
            // A tiebreaker, because created_at is not unique on a fast machine and two codes
            // made in the same second would otherwise swap lines between requests.
            ->orderByDesc('id')
            ->get();
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
