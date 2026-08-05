<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class AddProductToBasketAction
{
    public function execute(Basket $basket, Product $product, int $quantity = 1): BasketLine
    {
        if ($quantity < 1) {
            throw new RuntimeException('Quantity must be at least 1.');
        }

        // One definition, checked here and again at placement. A product that 404s on
        // the storefront must not be sellable through a posted form.
        if (! $product->isPurchasable()) {
            throw new RuntimeException(
                "{$product->name} {$product->unpurchasableReason()}."
            );
        }

        $line = DB::transaction(function () use ($basket, $product, $quantity): BasketLine {
            $existing = BasketLine::query()
                ->where('basket_id', $basket->id)
                ->where('product_id', $product->id)
                ->first();

            if ($existing !== null) {
                $existing->update(['quantity' => $existing->quantity + $quantity]);

                return $existing;
            }

            return BasketLine::create([
                'basket_id' => $basket->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                // Snapshot the price the shopper is being shown. Net of VAT, and
                // taken from the database rather than from the form — a posted price
                // is a suggestion from a stranger.
                'unit_price_minor' => $product->sale_price_minor ?? $product->price_minor,
            ]);
        });

        Log::info('ordering.add_to_basket.success', [
            'basket_id' => $basket->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'quantity' => $quantity,
        ]);

        return $line;
    }
}
