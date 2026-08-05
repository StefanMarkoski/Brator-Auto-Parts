<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use Illuminate\Support\Facades\Session;

/**
 * The theme's "Recently Viewed" strip, made real.
 *
 * Kept in the session and given no database table: shoppers are guests, so there is
 * nothing to attach a browsing history to, and storing one anyway would mean holding
 * behavioural data about people who never asked us to.
 */
final class RecentlyViewed
{
    public const SESSION_KEY = 'recently_viewed';

    private const LIMIT = 12;

    public function remember(string $productId): void
    {
        $ids = array_values(array_diff($this->all(), [$productId]));
        array_unshift($ids, $productId);

        Session::put(self::SESSION_KEY, array_slice($ids, 0, self::LIMIT));
    }

    /**
     * @param  string|null  $excluding  usually the product being looked at — "recently
     *                                  viewed" listing the page you are on is noise
     * @return list<string>
     */
    public function all(?string $excluding = null): array
    {
        $ids = array_values(array_filter((array) Session::get(self::SESSION_KEY, []), 'is_string'));

        return $excluding === null
            ? $ids
            : array_values(array_diff($ids, [$excluding]));
    }
}
