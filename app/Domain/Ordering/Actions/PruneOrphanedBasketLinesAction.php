<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\BasketLine;
use Illuminate\Support\Facades\Log;

/**
 * Removes basket lines whose product no longer exists.
 *
 * Skipping them at render time keeps the cart usable, but a line the shopper cannot see
 * and cannot remove is still a line that will surprise somebody later. This clears them
 * properly, and tells the shopper what happened rather than quietly shrinking the cart.
 */
final class PruneOrphanedBasketLinesAction
{
    /** @return int  how many lines were removed */
    public function execute(string $basketId): int
    {
        $orphaned = BasketLine::query()
            ->where('basket_id', $basketId)
            ->whereDoesntHave('product')
            ->pluck('id');

        if ($orphaned->isEmpty()) {
            return 0;
        }

        BasketLine::query()->whereIn('id', $orphaned)->delete();

        Log::info('ordering.prune_orphaned_basket_lines.removed', [
            'basket_id' => $basketId,
            'lines' => $orphaned->count(),
        ]);

        return $orphaned->count();
    }
}
