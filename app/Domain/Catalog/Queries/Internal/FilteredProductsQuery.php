<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Internal;

use App\Domain\Catalog\DTOs\ProductCardData;
use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCrossReference;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The filtered catalogue read.
 *
 * Written as INTERSECTING NARROW SUBQUERIES rather than one WHERE with five joins,
 * which is the discipline the schema plan (§9) calls for. MySQL uses one index per
 * table per query, so a shopper filtering category + brand + two attributes + price
 * cannot be served by a single index. Each filter is resolved through its own covering
 * index and the results are intersected on product_id; written naively, the optimizer
 * picks one index and scans for the rest.
 */
final class FilteredProductsQuery
{
    public function __construct(private ListProductCardsQuery $cards) {}

    /** @return Collection<int, ProductCardData> */
    public function page(ProductFilter $filter, int $perPage): Collection
    {
        $ids = $this->base($filter)
            ->select('products.id')
            ->offset(($filter->page - 1) * $perPage)
            ->limit($perPage)
            ->pluck('id')
            ->all();

        return $this->cards->forIds($ids);
    }

    public function count(ProductFilter $filter): int
    {
        return $this->base($filter, sorted: false)->distinct()->count('products.id');
    }

    /**
     * Facet counts: how many products each remaining option would give.
     *
     * Counted against the filter with THAT group removed, which is what makes the
     * numbers useful — a shopper who has already ticked "OEM" wants to know how many
     * "Aftermarket" parts there are, not zero.
     *
     * @return array{brands: array<string, int>, attributes: array<string, array<string, int>>, ratings: array<int, int>}
     */
    public function facets(ProductFilter $filter, array $attributeCodes): array
    {
        $brands = $this->base($this->without($filter, brands: true), sorted: false)
            ->join('brands as fb', 'fb.id', '=', 'products.brand_id')
            ->select('fb.slug', DB::raw('COUNT(DISTINCT products.id) as total'))
            ->groupBy('fb.slug')
            ->pluck('total', 'slug')
            ->map(fn ($n) => (int) $n)
            ->all();

        $attributes = [];

        foreach ($attributeCodes as $code) {
            $attributes[$code] = $this->base($this->without($filter, attribute: $code), sorted: false)
                ->join('product_attribute_values as fav', 'fav.product_id', '=', 'products.id')
                ->join('attributes as fa', 'fa.id', '=', 'fav.attribute_id')
                ->where('fa.code', $code)
                ->select('fav.value_string', DB::raw('COUNT(DISTINCT products.id) as total'))
                ->whereNotNull('fav.value_string')
                ->groupBy('fav.value_string')
                ->pluck('total', 'value_string')
                ->map(fn ($n) => (int) $n)
                ->all();
        }

        $ratings = [];

        foreach ([4, 3, 2, 1] as $stars) {
            $ratings[$stars] = $this->base($this->without($filter, rating: true), sorted: false)
                ->where('products.rating_avg', '>=', $stars)
                ->distinct()
                ->count('products.id');
        }

        return ['brands' => $brands, 'attributes' => $attributes, 'ratings' => $ratings];
    }

    /** The price range available under the current filter, for the slider's bounds. */
    public function priceBounds(ProductFilter $filter): array
    {
        $row = $this->base($this->without($filter, price: true), sorted: false)
            ->selectRaw('MIN(COALESCE(products.sale_price_minor, products.price_minor)) as lo, '
                .'MAX(COALESCE(products.sale_price_minor, products.price_minor)) as hi')
            ->first();

        return [
            'min' => (int) (($row->lo ?? 0) / 100),
            'max' => (int) ceil(($row->hi ?? 0) / 100),
        ];
    }

    private function base(ProductFilter $filter, bool $sorted = true): Builder
    {
        $query = DB::table('products');
        Product::scopeVisibleRaw($query);

        if ($filter->categoryPath !== null) {
            $query->whereIn('products.id', function (Builder $sub) use ($filter): void {
                $sub->select('pc.product_id')
                    ->from('product_categories as pc')
                    ->join('categories as c', 'c.id', '=', 'pc.category_id')
                    ->where('c.path', 'like', $filter->categoryPath.'%');
            });
        }

        if ($filter->brandSlugs !== []) {
            $query->whereIn('products.brand_id', function (Builder $sub) use ($filter): void {
                $sub->select('id')->from('brands')->whereIn('slug', $filter->brandSlugs);
            });
        }

        // Filter and sort on the EFFECTIVE price — the number the shopper actually
        // pays. Comparing against price_minor alone was a real bug: a discounted part
        // was filtered and ordered by its list price while the card displayed the sale
        // price, so "sort by price, low to high" produced a visibly wrong sequence.
        // Caught by a test that only failed on runs where the seed happened to put a
        // sale item in the sample.
        if ($filter->priceMinMinor !== null) {
            $query->whereRaw('COALESCE(products.sale_price_minor, products.price_minor) >= ?', [$filter->priceMinMinor]);
        }

        if ($filter->priceMaxMinor !== null) {
            $query->whereRaw('COALESCE(products.sale_price_minor, products.price_minor) <= ?', [$filter->priceMaxMinor]);
        }

        if ($filter->minRating !== null) {
            $query->where('products.rating_avg', '>=', $filter->minRating);
        }

        // One intersecting subquery per attribute GROUP. Values inside a group are OR
        // (tick two diameters, get both); separate groups are AND (diameter AND
        // material), which is what a shopper expects from a filter sidebar.
        foreach ($filter->attributes as $code => $values) {
            $query->whereIn('products.id', function (Builder $sub) use ($code, $values): void {
                $sub->select('pav.product_id')
                    ->from('product_attribute_values as pav')
                    ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
                    ->where('a.code', $code)
                    ->whereIn('pav.value_string', $values);
            });
        }

        if ($filter->vehicleVariantId !== null) {
            // The clustered range scan on product_vehicle_fitments — vehicle first.
            $query->whereIn('products.id', function (Builder $sub) use ($filter): void {
                $sub->select('product_id')
                    ->from('product_vehicle_fitments')
                    ->where('vehicle_variant_id', $filter->vehicleVariantId);
            });
        }

        if ($filter->searchTerm !== null) {
            $normalised = ProductCrossReference::normalise($filter->searchTerm);
            $term = $filter->searchTerm;

            $query->where(function (Builder $outer) use ($normalised, $term): void {
                $outer->whereIn('products.id', function (Builder $sub) use ($normalised): void {
                    $sub->select('product_id')
                        ->from('product_cross_references')
                        ->where('number_normalized', 'like', $normalised.'%');
                })->orWhere('products.name', 'like', '%'.$term.'%');
            });
        }

        if ($sorted) {
            match ($filter->sort) {
                'price_asc' => $query->orderByRaw('COALESCE(products.sale_price_minor, products.price_minor) ASC'),
                'price_desc' => $query->orderByRaw('COALESCE(products.sale_price_minor, products.price_minor) DESC'),
                'rating' => $query->orderByDesc('products.rating_avg'),
                'name' => $query->orderBy('products.name'),
                default => $query->orderByDesc('products.published_at'),
            };
        }

        return $query;
    }

    /** The same filter with one group lifted, for that group's own facet counts. */
    private function without(
        ProductFilter $filter,
        bool $brands = false,
        ?string $attribute = null,
        bool $rating = false,
        bool $price = false,
    ): ProductFilter {
        $attributes = $filter->attributes;

        if ($attribute !== null) {
            unset($attributes[$attribute]);
        }

        return ProductFilter::fromArray([
            'categoryPath' => $filter->categoryPath,
            'categorySlug' => $filter->categorySlug,
            'brandSlugs' => $brands ? [] : $filter->brandSlugs,
            'priceMinMinor' => $price ? null : $filter->priceMinMinor,
            'priceMaxMinor' => $price ? null : $filter->priceMaxMinor,
            'attributes' => $attributes,
            'minRating' => $rating ? null : $filter->minRating,
            'vehicleVariantId' => $filter->vehicleVariantId,
            'searchTerm' => $filter->searchTerm,
            'sort' => $filter->sort,
            'page' => 1,
            'listView' => $filter->listView,
        ]);
    }
}
