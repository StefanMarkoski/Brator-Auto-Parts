<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\Internal\ListProductCardsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CategoryController
{
    private const PER_PAGE = 12;

    public function __construct(private ListProductCardsQuery $cards) {}

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

    /** A category listing. `view=list` switches to the theme's list layout. */
    public function show(Request $request, string $slug): View
    {
        $category = Category::query()
            ->where('slug', $slug)->where('is_active', true)
            ->with('parent')
            ->firstOrFail();

        $page = max(1, (int) $request->query('page', 1));
        $listView = $request->query('view') === 'list';

        $total = $this->cards->countInCategorySubtree($category->path);

        return view($listView ? 'shop.listing-list' : 'shop.listing-grid', [
            'category' => $category,
            'breadcrumbs' => $this->breadcrumbsFor($category),
            'products' => $this->cards->inCategorySubtree(
                $category->path,
                self::PER_PAGE,
                ($page - 1) * self::PER_PAGE
            ),
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / self::PER_PAGE)),
            'listView' => $listView,
        ]);
    }
}
