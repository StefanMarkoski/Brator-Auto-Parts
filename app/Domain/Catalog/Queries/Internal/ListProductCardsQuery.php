<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Internal;

use App\Domain\Catalog\DTOs\ProductCardData;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCrossReference;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place product cards are read.
 *
 * Everything that renders a card — homepage strips, category pages, listings,
 * recommendation blocks — goes through here, so the column list and the join shape
 * live in a single file rather than being re-derived (and re-broken) per controller.
 *
 * Deliberately the query builder rather than Eloquent: cards need eleven columns from
 * products plus a brand name and one image. Hydrating full models to throw most of it
 * away is the cost that makes catalogue pages feel slow.
 */
final class ListProductCardsQuery
{
    /** @return Collection<int, ProductCardData> */
    public function forIds(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $rows = $this->base()->whereIn('products.id', $productIds)->get();

        // Preserve the caller's order — a curated strip's whole value is its order,
        // and SQL will not honour an IN() list's sequence.
        $byId = $rows->keyBy('id');

        return collect($productIds)
            ->map(fn (string $id) => $byId->get($id))
            ->filter()
            ->map(fn (object $row) => ProductCardData::fromRow($row))
            ->values();
    }

    /** @return Collection<int, ProductCardData> */
    public function newest(int $limit): Collection
    {
        return $this->base()
            ->orderByDesc('products.published_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => ProductCardData::fromRow($row));
    }

    /**
     * Best sellers, from what people actually bought. Falls back to nothing rather
     * than to random products: an empty strip is honest, a fake one is not.
     *
     * @return Collection<int, ProductCardData>
     */
    public function bestSelling(int $limit): Collection
    {
        $ids = DB::table('receipt_lines')
            ->whereNotNull('product_id')
            ->select('product_id', DB::raw('SUM(quantity) AS units'))
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit($limit)
            ->pluck('product_id')
            ->all();

        return $this->forIds($ids);
    }

    /**
     * Site search. Tries the part number first, because pasting the number off the
     * old part is the most common real search on a parts site — and an exact
     * cross-reference hit beats any fuzzy name match. Only falls back to the name
     * fulltext index when the number lookup finds nothing.
     *
     * @return Collection<int, ProductCardData>
     */
    public function search(string $term, int $limit, int $offset = 0): Collection
    {
        $ids = $this->matchingIds($term, $limit, $offset);

        return $this->forIds($ids);
    }

    public function countSearch(string $term): int
    {
        $normalised = ProductCrossReference::normalise($term);

        $byNumber = DB::table('product_cross_references')
            ->where('number_normalized', 'like', $normalised.'%')
            ->distinct()->count('product_id');

        if ($byNumber > 0) {
            return $byNumber;
        }

        return DB::table('products')
            ->tap(fn ($q) => Product::scopeVisibleRaw($q))
            ->whereFullText(['name', 'sku'], $term)
            ->count();
    }

    /** @return list<string> */
    private function matchingIds(string $term, int $limit, int $offset): array
    {
        $normalised = ProductCrossReference::normalise($term);

        if ($normalised !== '') {
            $byNumber = DB::table('product_cross_references')
                ->where('number_normalized', 'like', $normalised.'%')
                ->orderBy('product_id')
                ->offset($offset)->limit($limit)
                ->pluck('product_id')->unique()->values()->all();

            if ($byNumber !== []) {
                return $byNumber;
            }
        }

        return DB::table('products')
            ->tap(fn ($q) => Product::scopeVisibleRaw($q))
            ->whereFullText(['name', 'sku'], $term)
            ->offset($offset)->limit($limit)
            ->pluck('id')->all();
    }

    /** @return Collection<int, ProductCardData> */
    public function inCategorySubtree(string $categoryPath, int $limit, int $offset = 0): Collection
    {
        return $this->base()
            ->join('product_categories as pc', 'pc.product_id', '=', 'products.id')
            ->join('categories as cat', 'cat.id', '=', 'pc.category_id')
            ->where('cat.path', 'like', $categoryPath.'%')
            ->distinct()
            ->orderByDesc('products.published_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => ProductCardData::fromRow($row));
    }

    public function countInCategorySubtree(string $categoryPath): int
    {
        return DB::table('products')
            ->join('product_categories as pc', 'pc.product_id', '=', 'products.id')
            ->join('categories as cat', 'cat.id', '=', 'pc.category_id')
            ->tap(fn ($q) => Product::scopeVisibleRaw($q))
            ->where('cat.path', 'like', $categoryPath.'%')
            ->distinct()
            ->count('products.id');
    }

    /**
     * Explicit columns, never `*`. products.description is longText and pulling it
     * into a 24-card page is the single easiest way to make the catalogue slow.
     */
    private function base(): Builder
    {
        return DB::table('products')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('product_images', function ($join) {
                $join->on('product_images.product_id', '=', 'products.id')
                    ->where('product_images.is_primary', true);
            })
            ->tap(fn ($q) => Product::scopeVisibleRaw($q))
            ->select([
                'products.id', 'products.slug', 'products.name',
                'products.price_minor', 'products.sale_price_minor',
                'products.rating_avg', 'products.reviews_count',
                'products.stock_status', 'products.published_at',
                'brands.name as brand_name',
                'product_images.path as image_path',
            ]);
    }
}
