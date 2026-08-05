<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Internal;

use App\Domain\Catalog\DTOs\ProductCardData;
use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCrossReference;
use App\Domain\Fitment\Queries\Public\GetProductIdsForVehicleQuery;
use App\Support\Database\LikePattern;
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
    public function __construct(
        private ListProductCardsQuery $cards,
        private GetProductIdsForVehicleQuery $vehicleFitment,
    ) {}

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

        // Four separate COUNT queries collapsed into one pass. The review measured
        // facet counting at 157ms of a ~225ms unfiltered listing, of which these four
        // were a meaningful share — and they all scan the same set, just with different
        // thresholds, so one query with four conditional sums does the same work once.
        $row = $this->base($this->without($filter, rating: true), sorted: false)
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN products.rating_avg >= 4 THEN products.id END) as r4, '
                .'COUNT(DISTINCT CASE WHEN products.rating_avg >= 3 THEN products.id END) as r3, '
                .'COUNT(DISTINCT CASE WHEN products.rating_avg >= 2 THEN products.id END) as r2, '
                .'COUNT(DISTINCT CASE WHEN products.rating_avg >= 1 THEN products.id END) as r1'
            )
            ->first();

        $ratings = [
            4 => (int) ($row->r4 ?? 0),
            3 => (int) ($row->r3 ?? 0),
            2 => (int) ($row->r2 ?? 0),
            1 => (int) ($row->r1 ?? 0),
        ];

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
            // Fitment owns what "fits" means, so the shape of this question comes from
            // Fitment's public read API rather than being re-derived here. It is still a
            // subquery, so the clustered range scan on the vehicle-first key is intact.
            $query->whereIn(
                'products.id',
                $this->vehicleFitment->subqueryFor($filter->vehicleVariantId)
            );
        }

        if ($filter->searchTerm !== null) {
            $normalised = ProductCrossReference::normalise($filter->searchTerm);
            $term = $filter->searchTerm;

            $query->where(function (Builder $outer) use ($normalised, $term): void {
                // Only join the part-number branch when there is a number left to match.
                // normalise() strips everything but letters and digits, so a search for
                // "%" or "_" reduced to an empty string — and "starts with nothing"
                // matched all 5,000 products. Escaping the wildcard was necessary but
                // not sufficient; the empty term was the real cause.
                if ($normalised !== '') {
                    $outer->whereIn('products.id', function (Builder $sub) use ($normalised): void {
                        $sub->select('product_id')
                            ->from('product_cross_references')
                            ->where('number_normalized', 'like', LikePattern::startsWith($normalised));
                    })
                        ->orWhere('products.name', 'like', LikePattern::contains($term))
                        ->orWhere('products.sku', 'like', LikePattern::contains($term));

                    return;
                }

                $outer->where('products.name', 'like', LikePattern::contains($term))
                    ->orWhere('products.sku', 'like', LikePattern::contains($term));
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
