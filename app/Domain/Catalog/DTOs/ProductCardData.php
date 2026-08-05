<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything a product card needs and nothing else.
 *
 * Views receive these rather than Eloquent models, which is what stops a template
 * lazy-loading a relation 24 times on a listing page. It also means the listing query
 * can select an explicit column list — see Product::LISTING_COLUMNS.
 */
final readonly class ProductCardData
{
    private function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $brandName,
        public string $imagePath,
        public Money $price,
        public ?Money $originalPrice,
        public int $stars,
        public int $reviewsCount,
        public bool $inStock,
        /**
         * Theme class + label pairs. The theme stacks these — one of its own cards
         * carries both "20% OFF" and "Out OF stock" — so this is a list, not one badge.
         * Only ever classes the theme already ships; a new class would be a style change.
         *
         * @var list<array{class: string, label: string}>
         */
        public array $badges,
    ) {}

    /**
     * @param  object{id: string, slug: string, name: string, brand_name: ?string, image_path: ?string, price_minor: int, sale_price_minor: ?int, rating_avg: float|string, reviews_count: int, stock_status: string, published_at: ?string}  $row
     */
    public static function fromRow(object $row): self
    {
        $price = Money::fromMinor((int) $row->price_minor);
        $sale = $row->sale_price_minor === null ? null : Money::fromMinor((int) $row->sale_price_minor);
        $inStock = $row->stock_status !== 'out_of_stock';

        $badges = self::badgesFor($inStock, $price, $sale, $row->published_at ?? null, $row->units_sold ?? null);

        return new self(
            id: $row->id,
            slug: $row->slug,
            name: $row->name,
            brandName: $row->brand_name ?? null,
            // The theme's own placeholder is the fallback, so a product without an
            // image still renders a card the right size instead of a collapsed box.
            imagePath: $row->image_path ?? 'assets/images/shop/product-06.jpg',
            price: $sale ?? $price,
            originalPrice: $sale === null ? null : $price,
            stars: (int) round((float) $row->rating_avg),
            reviewsCount: (int) $row->reviews_count,
            inStock: $inStock,
            badges: $badges,
        );
    }

    public static function fromModel(Model $product, ?string $brandName = null, ?string $imagePath = null): self
    {
        return self::fromRow((object) [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand_name' => $brandName,
            'image_path' => $imagePath,
            'price_minor' => $product->getRawOriginal('price_minor'),
            'sale_price_minor' => $product->getRawOriginal('sale_price_minor'),
            'rating_avg' => $product->rating_avg,
            'reviews_count' => $product->reviews_count,
            'stock_status' => $product->getRawOriginal('stock_status'),
            'published_at' => $product->getRawOriginal('published_at'),
        ]);
    }

    /**
     * Badges, in the theme's own vocabulary and its own stacking order.
     *
     * A discount and an out-of-stock notice appear together — the theme does exactly
     * that on one of its demo cards. "New" is suppressed when there is a discount to
     * show instead, because two competing claims on one small card reads as noise.
     *
     * @return list<array{class: string, label: string}>
     */
    private static function badgesFor(
        bool $inStock,
        Money $price,
        ?Money $sale,
        ?string $publishedAt,
        ?int $unitsSold,
    ): array {
        $badges = [];

        if ($sale !== null && $price->minor > 0) {
            $off = (int) round((1 - $sale->minor / $price->minor) * 100);

            if ($off > 0) {
                $badges[] = ['class' => 'off-batch', 'label' => $off.'% OFF'];
            }
        }

        if ($badges === [] && $publishedAt !== null && strtotime($publishedAt) > strtotime('-30 days')) {
            $badges[] = ['class' => 'yollow-batch', 'label' => 'New'];
        }

        if ($unitsSold !== null && $unitsSold > 0) {
            $badges[] = [
                'class' => 'brator-product-batch stock-number-batch',
                'label' => number_format($unitsSold).' Sold',
            ];
        }

        if (! $inStock) {
            $badges[] = ['class' => 'stock-out-batch', 'label' => 'Out OF stock'];
        }

        return $badges;
    }
}
