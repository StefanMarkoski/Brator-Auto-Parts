<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

/**
 * Money as integer minor units. Never a float — 0.1 + 0.2 is not 0.3, and a shop
 * that adds prices in floats will eventually disagree with its own receipts.
 *
 * All amounts in this application are NET of VAT unless a variable says otherwise.
 */
final readonly class Money
{
    private function __construct(public int $minor) {}

    public static function fromMinor(int $minor): self
    {
        if ($minor < 0) {
            throw new InvalidArgumentException("Money cannot be negative, got {$minor}.");
        }

        return new self($minor);
    }

    public static function fromMajor(int|float|string $major): self
    {
        return self::fromMinor((int) round((float) $major * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        return self::fromMinor($this->minor - $other->minor);
    }

    public function timesQuantity(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Quantity cannot be negative, got {$quantity}.");
        }

        return new self($this->minor * $quantity);
    }

    /**
     * VAT on this amount, rounded half-up to the minor unit.
     *
     * Callers must apply this PER LINE and then sum, never to an order total:
     * the two give different answers, and the difference is impossible to explain
     * to whoever spots it on an invoice.
     */
    public function vatAt(float $ratePercent): self
    {
        if ($ratePercent < 0) {
            throw new InvalidArgumentException("VAT rate cannot be negative, got {$ratePercent}.");
        }

        return new self((int) round($this->minor * $ratePercent / 100, 0, PHP_ROUND_HALF_UP));
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function toMajor(): float
    {
        return $this->minor / 100;
    }

    public function toPrimitive(): int
    {
        return $this->minor;
    }

    public function format(): string
    {
        return number_format($this->toMajor(), 2, ',', '.').' '.config('shop.currency_symbol');
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
