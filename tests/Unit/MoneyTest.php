<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ValueObjects\Money;
use InvalidArgumentException;
use Tests\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_refuses_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinor(-1);
    }

    public function test_vat_is_rounded_half_up(): void
    {
        // 1000 minor at 18% = 180 exactly.
        $this->assertSame(180, Money::fromMinor(1_000)->vatAt(18)->minor);

        // 1005 minor at 18% = 180.9 -> 181. Rounding down here would under-collect
        // VAT on nearly every line of every receipt.
        $this->assertSame(181, Money::fromMinor(1_005)->vatAt(18)->minor);
    }

    public function test_per_line_vat_and_order_total_vat_genuinely_differ(): void
    {
        // This is why the rule is written down rather than left to whoever writes the
        // checkout. The same three lines, VAT taken two different ways, disagree — and
        // on a real invoice that difference is money nobody can account for.
        $lines = [Money::fromMinor(1_007), Money::fromMinor(1_007), Money::fromMinor(1_007)];

        $perLine = array_reduce(
            $lines,
            fn (Money $carry, Money $line): Money => $carry->add($line->vatAt(18)),
            Money::zero()
        );

        $onTotal = array_reduce(
            $lines,
            fn (Money $carry, Money $line): Money => $carry->add($line),
            Money::zero()
        )->vatAt(18);

        // Per line: 1007 * 18% = 181.26 -> 181, three times = 543.
        $this->assertSame(543, $perLine->minor);
        // The same money summed first: 3021 * 18% = 543.78 -> 544. One denar apart.
        $this->assertSame(544, $onTotal->minor);
        $this->assertNotSame($perLine->minor, $onTotal->minor,
            'If these ever match, the example needs new numbers — the POINT is that the '
            .'two methods disagree, which is why the schema plan fixes VAT as per-line.');
    }

    public function test_quantity_multiplication_and_addition(): void
    {
        $unit = Money::fromMinor(2_500);

        $this->assertSame(7_500, $unit->timesQuantity(3)->minor);
        $this->assertSame(10_000, $unit->timesQuantity(3)->add($unit)->minor);
    }

    public function test_it_is_immutable(): void
    {
        $original = Money::fromMinor(1_000);
        $original->add(Money::fromMinor(500));

        $this->assertSame(1_000, $original->minor);
    }
}
