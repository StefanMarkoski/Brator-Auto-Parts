<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Queries\Internal\FilteredProductsQuery;
use App\Domain\Catalog\Queries\Internal\GetCategoryFiltersQuery;
use App\Domain\Catalog\Queries\Internal\ListProductCardsQuery;
use App\Domain\Catalog\Services\RecentlyViewed;
use App\Domain\Fitment\Queries\Internal\GetVehiclePickerQuery;

/**
 * Builds the payload a listing page needs, so the category listing and the search
 * results cannot drift apart. Both show the same sidebar, the same counts and the
 * same vehicle bar — one place to get that right rather than two.
 */
final class ListingPayload
{
    public function __construct(
        private FilteredProductsQuery $products,
        private GetCategoryFiltersQuery $categoryFilters,
        private GetVehiclePickerQuery $vehiclePicker,
        private ListProductCardsQuery $cards,
        private RecentlyViewed $recentlyViewed,
    ) {}

    /**
     * Page numbers to show, with nulls standing in for gaps.
     *
     * @return list<int|null>
     */
    private function window(int $current, int $last): array
    {
        if ($last <= 7) {
            return range(1, $last);
        }

        $window = [1];

        if ($current > 3) {
            $window[] = null;
        }

        foreach (range(max(2, $current - 1), min($last - 1, $current + 1)) as $number) {
            $window[] = $number;
        }

        if ($current < $last - 2) {
            $window[] = null;
        }

        $window[] = $last;

        return $window;
    }

    /** @return array<string, mixed> */
    public function build(ProductFilter $filter, ?string $categoryId = null): array
    {
        $filters = $this->categoryFilters->execute($categoryId);
        $codes = array_column($filters, 'code');

        $total = $this->products->count($filter);
        $perPage = $filter->perPage;

        return [
            'filter' => $filter,
            'products' => $this->products->page($filter, $perPage),
            'total' => $total,
            'page' => $filter->page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
            // The range actually shown, for the theme's results line — which shipped
            // hardcoded as "1 - 40 of 1,652 results".
            'shownFrom' => $total === 0 ? 0 : (($filter->page - 1) * $perPage) + 1,
            'shownTo' => min($total, $filter->page * $perPage),
            // A short window rather than every page number: 13 pages is fine to list,
            // 400 is not, and the theme's markup has room for about seven.
            'paginationWindow' => $this->window($filter->page, max(1, (int) ceil($total / $perPage))),
            'listView' => $filter->listView,
            'filterGroups' => $filters,
            'facets' => $this->products->facets($filter, $codes),
            'priceBounds' => $this->products->priceBounds($filter),
            'brands' => Brand::query()->where('is_active', true)
                ->orderBy('name')->get(['name', 'slug']),
            'vehicle' => $this->vehiclePicker->selection($filter->vehicleVariantId),
            // The theme's sidebar 'Best Seller' widget and its 'Recently Viewed'
            // strip, both of which shipped as hardcoded demo products.
            'bestSellers' => $this->cards->bestSelling(4),
            'recentlyViewed' => $this->cards->forIds($this->recentlyViewed->all()),
        ];
    }
}
