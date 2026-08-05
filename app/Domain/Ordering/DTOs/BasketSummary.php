<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTOs;

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
    /** Free delivery threshold, in minor units (3.000 ден). */
    private const FREE_SHIPPING_FROM = 300_000;

    private const SHIPPING_FLAT = 19_000;

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

        $shipping = Money::fromMinor(
            $subtotal->minor >= self::FREE_SHIPPING_FROM || $subtotal->isZero()
                ? 0
                : self::SHIPPING_FLAT
        );

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

    public function qualifiesForFreeShipping(): bool
    {
        return $this->shipping->isZero() && ! $this->isEmpty();
    }
}
