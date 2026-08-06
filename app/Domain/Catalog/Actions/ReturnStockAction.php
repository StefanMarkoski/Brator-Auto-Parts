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
 * Puts stock back on the shelf when an order is cancelled.
 *
 * The mirror of RecordStockSaleAction, and it lives in Catalog for the same reason: stock
 * is Catalog's to change. Ordering knows an order was cancelled; it does not get to decide
 * what that means for a shelf.
 *
 * Restocking also has to clear out_of_stock. A cancelled order returning the last two units
 * of a part that sold out would otherwise leave it sitting at quantity 2 and still marked
 * unbuyable — invisible to every shopper, with nothing on screen explaining why.
 *
 * Idempotency is NOT handled here. It belongs to the caller, which knows whether the
 * receipt has already been cancelled; this action cannot tell a legitimate second
 * cancellation of a different order from a double-clicked button.
 */
final class ReturnStockAction
{
    public function execute(string $productId, int $quantity, string $reference, string $note): void
    {
        if ($quantity < 1) {
            throw new RuntimeException("Cannot return {$quantity} of a product.");
        }

        DB::transaction(function () use ($productId, $quantity, $reference, $note): void {
            // withTrashed: a product deleted after the order was placed still has to
            // accept its stock back, or cancelling that order fails outright.
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($productId);

            StockMovement::create([
                'product_id' => $product->id,
                'delta' => $quantity,
                'reason' => StockMovementReason::Cancellation,
                'reference_type' => 'receipt',
                'reference_id' => $reference,
                'note' => $note,
            ]);

            $product->update([
                'stock_quantity' => (int) $product->stock_quantity + $quantity,
                'stock_status' => $product->stock_status === StockStatus::OutOfStock
                    ? StockStatus::InStock
                    : $product->stock_status,
            ]);

            Log::info('catalog.return_stock.success', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'now' => (int) $product->stock_quantity,
            ]);
        });
    }
}
