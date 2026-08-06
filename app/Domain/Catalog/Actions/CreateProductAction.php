<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\StockMovementReason;
use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Adds a product to the catalogue.
 *
 * Two things here are deliberate rather than incidental.
 *
 * The slug is derived and made unique HERE, not in the controller. It is part of what a
 * product is — a URL the shop can serve — and the storefront joins on it, so a duplicate
 * is a 404 or a hijacked page rather than a validation nicety. A unique index backs it up.
 *
 * Opening stock arrives as a stock MOVEMENT, not just a number written onto the row. Every
 * other change to stock_quantity is ledgered (sales, cancellations, adjustments), so a
 * product whose initial 40 units appeared from nowhere would leave the ledger unable to
 * explain the quantity it is meant to explain.
 */
final class CreateProductAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $categoryIds
     */
    public function execute(array $attributes, array $categoryIds = [], ?string $actorId = null): Product
    {
        return DB::transaction(function () use ($attributes, $categoryIds, $actorId): Product {
            $opening = (int) ($attributes['stock_quantity'] ?? 0);

            $product = Product::create([
                ...$attributes,
                'slug' => $this->uniqueSlug($attributes['slug'] ?? null, (string) $attributes['name']),
                // Start at zero and let the movement below carry it up, so the ledger and
                // the cached quantity are written by the same rule that governs every
                // later change.
                'stock_quantity' => 0,
                'stock_status' => $opening > 0
                    ? ($attributes['stock_status'] ?? StockStatus::InStock)
                    : StockStatus::OutOfStock,
            ]);

            if ($opening !== 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'delta' => $opening,
                    'reason' => StockMovementReason::Stocktake,
                    'reference_type' => 'user',
                    'reference_id' => $actorId,
                    'note' => 'Opening stock, set when the product was created.',
                ]);

                $product->update(['stock_quantity' => $opening]);
            }

            if ($categoryIds !== []) {
                $product->categories()->sync($categoryIds);
            }

            Log::info('catalog.create_product.success', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'opening_stock' => $opening,
                'categories' => count($categoryIds),
            ]);

            return $product;
        });
    }

    /**
     * A slug nobody else holds. Checks soft-deleted rows too: the unique index does not
     * care that a row is trashed, so ignoring them turns a legitimate create into a
     * duplicate-key 500.
     */
    private function uniqueSlug(?string $preferred, string $name): string
    {
        $base = Str::slug($preferred !== null && trim($preferred) !== '' ? $preferred : $name);
        $base = $base === '' ? 'product' : $base;

        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
