<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Removes a product from the shop.
 *
 * SOFT delete, always, and not out of caution about clicking the wrong row. Receipt lines
 * hold the product_id, and a hard delete would either break that reference or cascade away
 * a line from a sealed receipt. The snapshots (name, SKU, brand) mean an old receipt still
 * reads correctly, but the link has to survive too — reporting joins on it.
 *
 * `is_active` is cleared alongside the soft delete. scopeVisible() already excludes trashed
 * rows, so this is belt and braces for the raw query-builder reads: one of them checking
 * deleted_at and another checking is_active is exactly how a "deleted" product went on
 * selling before, when four definitions of visible disagreed.
 */
final class DeleteProductAction
{
    public function execute(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->update(['is_active' => false]);
            $product->delete();

            /*
             | Basket lines are NOT cleaned up here, and that is on purpose. A shopper mid-
             | checkout has this product in their basket; PlaceReceiptAction already drops
             | lines whose product has gone (the fault family that used to 500 the cart) and
             | tells them what happened. Deleting their lines from under them here would
             | make the basket silently shrink instead.
            */

            Log::info('catalog.delete_product.success', [
                'product_id' => $product->id,
                'sku' => $product->sku,
            ]);
        });
    }

    /** Put a deleted product back on the shelf, still unpublished until staff say so. */
    public function restore(Product $product): void
    {
        $product->restore();

        Log::info('catalog.restore_product.success', [
            'product_id' => $product->id,
            'sku' => $product->sku,
        ]);
    }
}
