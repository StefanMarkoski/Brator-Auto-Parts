<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries\Internal;

use App\Domain\Ordering\DTOs\BasketLineSummary;
use App\Domain\Ordering\DTOs\BasketSummary;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use App\Support\ValueObjects\Money;

final class GetBasketSummaryQuery
{
    public function execute(?Basket $basket): BasketSummary
    {
        if ($basket === null) {
            return BasketSummary::empty();
        }

        $lines = $basket->lines->map(function (BasketLine $line): BasketLineSummary {
            $product = $line->product;

            return new BasketLineSummary(
                lineId: $line->id,
                productId: $product->id,
                productSlug: $product->slug,
                productName: $product->name,
                productSku: $product->sku,
                brandName: $product->brand?->name,
                imagePath: $product->images->first()->path ?? 'assets/images/shop/product-06.jpg',
                // The price the shopper saw when they added it. Re-validated at
                // placement, so a mid-session price change cannot slip through.
                unitPrice: $line->unit_price_minor,
                quantity: $line->quantity,
                lineTotal: $line->unit_price_minor->timesQuantity($line->quantity),
                inStock: $product->stock_status->isBuyable(),
                stockAvailable: (int) $product->stock_quantity,
            );
        });

        return BasketSummary::fromLines($lines, (float) config('shop.vat_rate'));
    }

    /** Just the badge number for the header, without loading the whole basket. */
    public function itemCount(?Basket $basket): int
    {
        return $basket === null ? 0 : (int) $basket->lines->sum('quantity');
    }

    private function money(int $minor): Money
    {
        return Money::fromMinor($minor);
    }
}
