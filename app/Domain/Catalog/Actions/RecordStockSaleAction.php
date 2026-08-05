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
 * Takes stock off the shelf for a sale, and owns the rule that stock cannot go negative.
 *
 * This exists because of the reviewer's sharpest point. Ordering was writing Catalog's
 * Product and StockMovement rows directly, which the DDD spec forbids (§2: cross-context
 * writes go through the owning context). That is not folder pedantry — the practical
 * consequence was that stock's "never below zero" invariant had no home, so nobody wrote
 * it, and a posted quantity drove stock_quantity to -498.
 *
 * The ledger row and the cached quantity are written together, under a row lock, so two
 * simultaneous checkouts cannot both pass the check and oversell.
 *
 * Called synchronously rather than through an event, deliberately: the decrement must be
 * atomic with the receipt, and a queued listener cannot be inside that transaction.
 */
final class RecordStockSaleAction
{
    public function execute(string $productId, int $quantity, string $reference, string $note): void
    {
        if ($quantity < 1) {
            throw new RuntimeException("Cannot sell {$quantity} of a product.");
        }

        DB::transaction(function () use ($productId, $quantity, $reference, $note): void {
            // Locked: without this, two checkouts can both read 3-in-stock and both
            // decrement, and the shop has sold six of three.
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if ($quantity > (int) $product->stock_quantity) {
                throw new RuntimeException(
                    "Cannot sell {$quantity} of {$product->name}: only "
                    ."{$product->stock_quantity} in stock."
                );
            }

            StockMovement::create([
                'product_id' => $product->id,
                'delta' => -$quantity,
                'reason' => StockMovementReason::Sale,
                'reference_type' => 'receipt',
                'reference_id' => $reference,
                'note' => $note,
            ]);

            $remaining = (int) $product->stock_quantity - $quantity;

            $product->update([
                'stock_quantity' => $remaining,
                // The shelf is empty; say so rather than leaving it listed as in stock.
                'stock_status' => $remaining === 0
                    ? StockStatus::OutOfStock
                    : $product->stock_status,
            ]);

            Log::info('catalog.record_stock_sale.success', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'remaining' => $remaining,
            ]);
        });
    }
}
