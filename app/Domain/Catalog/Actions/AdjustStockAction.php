<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\StockMovementReason;
use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sets stock to a counted figure — the stocktake, not a sale.
 *
 * Takes the ABSOLUTE quantity rather than a delta, because that is what the person at the
 * shelf actually knows: they counted eleven. Asking staff to work out "+3" from a number
 * the screen already shows them is how the ledger ends up disagreeing with the shelf.
 *
 * The delta is derived and ledgered, so the movement history still explains every unit.
 * Same row lock as a sale: a stocktake landing at the same moment as a checkout must not
 * overwrite the decrement.
 */
final class AdjustStockAction
{
    public function execute(
        string $productId,
        int $countedQuantity,
        ?string $actorId = null,
        ?string $note = null,
    ): int {
        if ($countedQuantity < 0) {
            throw new RuntimeException("Stock cannot be set to {$countedQuantity}.");
        }

        return DB::transaction(function () use ($productId, $countedQuantity, $actorId, $note): int {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            $delta = $countedQuantity - (int) $product->stock_quantity;

            if ($delta === 0) {
                return 0;
            }

            StockMovement::create([
                'product_id' => $product->id,
                'delta' => $delta,
                'reason' => StockMovementReason::ManualAdjustment,
                'reference_type' => 'user',
                'reference_id' => $actorId,
                'note' => $note ?? 'Counted in the admin panel.',
            ]);

            $product->update([
                'stock_quantity' => $countedQuantity,
                /*
                 | Keep the status honest in both directions. Dropping to zero marks it out
                 | of stock, as a sale does — but a restock must also clear that flag, or a
                 | part that has arrived stays unbuyable and nobody can see why. The sale
                 | path only ever needed the first half, which is why this rule lives here
                 | rather than being shared.
                */
                'stock_status' => match (true) {
                    $countedQuantity === 0 => StockStatus::OutOfStock,
                    $product->stock_status === StockStatus::OutOfStock => StockStatus::InStock,
                    default => $product->stock_status,
                },
            ]);

            Log::info('catalog.adjust_stock.success', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'delta' => $delta,
                'counted' => $countedQuantity,
            ]);

            return $delta;
        });
    }
}
