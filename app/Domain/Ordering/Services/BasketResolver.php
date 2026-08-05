<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Services;

use App\Domain\Ordering\Models\Basket;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Finds (or starts) the current visitor's basket.
 *
 * Shoppers are guests — there are no customer accounts — so the basket is keyed by a
 * token held in the session. The session holds only the token; the basket itself lives
 * in the database, so nothing about it can be tampered with client-side.
 *
 * The token lives in the session rather than a hand-rolled cookie. A bare cookie was
 * the first attempt and it was the wrong call twice over: Laravel's session is already
 * a signed, encrypted cookie doing exactly this job, and a hand-rolled cookie is not
 * carried between requests by the test client — which meant the checkout flow could
 * not be tested end to end. Something that cannot be tested end to end is not
 * something to trust with money.
 *
 * Session lifetime therefore governs how long a basket survives; SESSION_LIFETIME is
 * set generously in .env for that reason.
 */
final class BasketResolver
{
    public const SESSION_KEY = 'basket_token';

    private const DAYS = 30;

    public function current(): ?Basket
    {
        $token = Session::get(self::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Basket::query()
            ->with(['lines.product.brand', 'lines.product.images'])
            ->where('session_token', $token)
            ->first();
    }

    /** Creates the basket on first add, so browsing never writes a row. */
    public function currentOrCreate(): Basket
    {
        $existing = $this->current();

        if ($existing !== null) {
            return $existing;
        }

        $token = (string) Str::ulid();
        Session::put(self::SESSION_KEY, $token);

        return Basket::create([
            'session_token' => $token,
            'expires_at' => now()->addDays(self::DAYS),
        ]);
    }
}
