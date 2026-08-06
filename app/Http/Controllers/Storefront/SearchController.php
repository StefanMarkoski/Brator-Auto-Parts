<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Category;
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

        /*
         | The department scope beside the search box.
         |
         | The theme shipped that dropdown filled with "ALL / US URO / US BD" — currency
         | options, in a search-scope control, on a single-currency shop — and the select had
         | no name attribute, so choosing anything submitted nothing. It now scopes the search
         | to a department, which is what its position on the page promises.
        */
        $department = null;
        $slug = trim((string) $request->query('in', ''));

        if ($slug !== '') {
            $department = Category::query()
                ->where('slug', $slug)->where('is_active', true)->first();

            if ($department !== null) {
                $filter = $filter->forCategory($department->slug, $department->path);
            }
        }

        return view($filter->listView ? 'shop.listing-list' : 'shop.listing-grid', [
            ...$this->listing->build($filter, $department?->id),
            'category' => $department,
            'searchTerm' => $filter->searchTerm,
            'breadcrumbs' => [
                'Search'.($filter->searchTerm === null ? '' : ': '.$filter->searchTerm) => null,
            ],
        ]);
    }
}
