<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\Basket;
use Illuminate\Support\Facades\Log;

/**
 * Brings a basket's snapshotted prices up to the live ones.
 *
 * Called only after the shopper has been TOLD a price changed. The snapshot exists so
 * nobody is charged a price they did not see; refreshing it silently would defeat the
 * point, so this is deliberately not called on every cart view.
 */
final class RefreshBasketPricesAction
{
    /** @return int  how many lines were repriced */
    public function execute(Basket $basket): int
    {
        $basket->loadMissing('lines.product');
        $changed = 0;

        foreach ($basket->lines as $line) {
            $product = $line->product;

            if ($product === null) {
                continue;
            }

            $live = $product->sale_price_minor ?? $product->price_minor;

            if ($live->equals($line->unit_price_minor)) {
                continue;
            }

            $line->update(['unit_price_minor' => $live]);
            $changed++;
        }

        if ($changed > 0) {
            Log::info('ordering.refresh_basket_prices.repriced', [
                'basket_id' => $basket->id,
                'lines' => $changed,
            ]);
        }

        return $changed;
    }
}
