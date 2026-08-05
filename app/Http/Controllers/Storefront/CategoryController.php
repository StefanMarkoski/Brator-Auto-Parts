<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Category;
use App\Domain\Fitment\Services\VehicleSelection;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CategoryController
{
    public function __construct(
        private ListingPayload $listing,
        private VehicleSelection $vehicle,
    ) {}

    /** The top-level category grid — the theme's shop-categories page. */
    public function index(): View
    {
        return view('shop.categories', [
            'breadcrumbs' => [],
            'categories' => Category::query()
                ->where('depth', 0)->where('is_active', true)
                ->with('children')
                ->orderBy('position')
                ->get(),
        ]);
    }

    /** A category listing. `view=list` switches to the theme's list layout. */
    public function show(Request $request, string $slug): View
    {
        $category = Category::query()
            ->where('slug', $slug)->where('is_active', true)
            ->with('parent')
            ->firstOrFail();

        $filter = ProductFilter::fromRequest($request, $this->vehicle->current())
            ->forCategory($category->slug, $category->path);

        return view($filter->listView ? 'shop.listing-list' : 'shop.listing-grid', [
            ...$this->listing->build($filter, $category->id),
            'category' => $category,
            'searchTerm' => null,
            'breadcrumbs' => $this->breadcrumbsFor($category),
        ]);
    }

    /**
     * Breadcrumb trail: parent, then the category itself as the active crumb.
     * Label => url, and a null url marks the active (unlinked) crumb.
     *
     * @return array<string, ?string>
     */
    private function breadcrumbsFor(Category $category): array
    {
        $crumbs = [];

        if ($category->parent !== null) {
            $crumbs[$category->parent->name] = route('shop.category', $category->parent->slug, false);
        }

        $crumbs[$category->name] = null;

        return $crumbs;
    }
}
