<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Fitment\Services\VehicleSelection;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SearchController
{
    public function __construct(
        private ListingPayload $listing,
        private VehicleSelection $vehicle,
    ) {}

    public function __invoke(Request $request): View
    {
        // The theme's search input is named "s"; keep that so its markup is untouched.
        $filter = ProductFilter::fromRequest($request, $this->vehicle->current());

        return view($filter->listView ? 'shop.listing-list' : 'shop.listing-grid', [
            ...$this->listing->build($filter),
            'category' => null,
            'searchTerm' => $filter->searchTerm,
            'breadcrumbs' => [
                'Search'.($filter->searchTerm === null ? '' : ': '.$filter->searchTerm) => null,
            ],
        ]);
    }
}
