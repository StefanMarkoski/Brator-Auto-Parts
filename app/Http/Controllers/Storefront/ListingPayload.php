<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Queries\Internal\FilteredProductsQuery;
use App\Domain\Catalog\Queries\Internal\GetCategoryFiltersQuery;
use App\Domain\Fitment\Queries\Internal\GetVehiclePickerQuery;

/**
 * Builds the payload a listing page needs, so the category listing and the search
 * results cannot drift apart. Both show the same sidebar, the same counts and the
 * same vehicle bar — one place to get that right rather than two.
 */
final class ListingPayload
{
    public const PER_PAGE = 12;

    public function __construct(
        private FilteredProductsQuery $products,
        private GetCategoryFiltersQuery $categoryFilters,
        private GetVehiclePickerQuery $vehiclePicker,
    ) {}

    /** @return array<string, mixed> */
    public function build(ProductFilter $filter, ?string $categoryId = null): array
    {
        $filters = $this->categoryFilters->execute($categoryId);
        $codes = array_column($filters, 'code');

        $total = $this->products->count($filter);

        return [
            'filter' => $filter,
            'products' => $this->products->page($filter, self::PER_PAGE),
            'total' => $total,
            'page' => $filter->page,
            'lastPage' => max(1, (int) ceil($total / self::PER_PAGE)),
            'listView' => $filter->listView,
            'filterGroups' => $filters,
            'facets' => $this->products->facets($filter, $codes),
            'priceBounds' => $this->products->priceBounds($filter),
            'brands' => Brand::query()->where('is_active', true)
                ->orderBy('name')->get(['name', 'slug']),
            'vehicle' => $this->vehiclePicker->selection($filter->vehicleVariantId),
        ];
    }
}
