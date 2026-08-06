<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\Coupon;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Creates a coupon with a code a person can actually use.
 *
 * "Ten characters that make sense" is the requirement, and it rules out a random hash. A
 * code gets typed on a phone keyboard and read aloud to a customer, so it is built as
 * SAVE<percent> plus filler from an alphabet with the confusable characters removed:
 *
 *   SAVE10K7QP     "save ten, kay seven queue pea"
 *   SAVE5R2MTKX    "save five, ..."
 *
 * No O or 0, no I, 1 or L — those are the pairs people get wrong, and a wrong character on
 * a discount code is a support call. Exactly ten characters, so the column is a fixed width
 * and the field on the cart can say what to expect.
 */
final class GenerateCouponAction
{
    private const LENGTH = 10;

    /** Deliberately missing O, 0, I, 1, L, and also U (reads as V when handwritten). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';

    private const MAX_ATTEMPTS = 20;

    public function execute(int $discountPercent, ?Money $minimumOrder = null): Coupon
    {
        if ($discountPercent < 1 || $discountPercent > 100) {
            throw new RuntimeException("A discount of {$discountPercent}% makes no sense.");
        }

        $coupon = Coupon::create([
            'code' => $this->uniqueCode($discountPercent),
            'discount_percent' => $discountPercent,
            'minimum_order_minor' => $minimumOrder?->toPrimitive(),
            'is_active' => true,
        ]);

        Log::info('ordering.generate_coupon.success', [
            'code' => $coupon->code,
            'percent' => $discountPercent,
            'minimum_minor' => $minimumOrder?->toPrimitive(),
        ]);

        return $coupon;
    }

    /**
     * A code nobody holds.
     *
     * Retried rather than assumed: the code is short and readable BECAUSE the alphabet is
     * small, which is exactly what makes a collision plausible enough to handle. Twenty
     * attempts over 30^4 to 30^5 possibilities is generous.
     */
    private function uniqueCode(int $discountPercent): string
    {
        $prefix = 'SAVE'.$discountPercent;
        $fillerLength = self::LENGTH - strlen($prefix);

        // A 100% coupon leaves "SAVE100" and three characters; single digits leave five.
        if ($fillerLength < 3) {
            $prefix = 'SAVE';
            $fillerLength = self::LENGTH - 4;
        }

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $prefix.$this->filler($fillerLength);

            if (! Coupon::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException(
            'Could not find an unused coupon code after '.self::MAX_ATTEMPTS.' tries.'
        );
    }

    private function filler(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            // random_int, not rand: a guessable discount code is a discount anybody can have.
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
