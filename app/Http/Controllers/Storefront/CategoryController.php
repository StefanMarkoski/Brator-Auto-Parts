<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\Internal\ListProductCardsQuery;
use App\Domain\Fitment\Services\VehicleSelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CategoryController
{
    public function __construct(
        private ListingPayload $listing,
        private VehicleSelection $vehicle,
        private ListProductCardsQuery $cards,
    ) {}

    /** The top-level category grid — the theme's shop-categories page. */
    public function index(): View
    {
        return view('shop.categories', [
            'breadcrumbs' => [],
            // The theme's featured strip on this page was hardcoded demo products.
            'featured' => $this->cards->bestSelling(5),
            'makes' => $this->vehicleMakes(),
            'totalProducts' => DB::table('products')
                ->where('is_active', true)->whereNull('deleted_at')->count(),
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
     * Vehicle makes for this page's "shop by make" list, which shipped as a wall of
     * "Accura" placeholders.
     *
     * @return list<array{slug: string, name: string}>
     */
    private function vehicleMakes(): array
    {
        return DB::table('vehicle_makes')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn ($row) => ['slug' => $row->slug, 'name' => $row->name])
            ->all();
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
