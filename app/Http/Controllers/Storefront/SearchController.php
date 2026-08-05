<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Queries\Internal\ListProductCardsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SearchController
{
    private const PER_PAGE = 12;

    public function __construct(private ListProductCardsQuery $cards) {}

    public function __invoke(Request $request): View
    {
        // The theme's search input is named "s"; keep that so its markup is untouched.
        $term = trim((string) ($request->query('s') ?? $request->query('q') ?? ''));
        $page = max(1, (int) $request->query('page', 1));
        $listView = $request->query('view') === 'list';

        $total = $term === '' ? 0 : $this->cards->countSearch($term);

        return view($listView ? 'shop.listing-list' : 'shop.listing-grid', [
            'category' => null,
            'searchTerm' => $term,
            'breadcrumbs' => ['Search'.($term === '' ? '' : ': '.$term) => null],
            'products' => $term === ''
                ? collect()
                : $this->cards->search($term, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / self::PER_PAGE)),
            'listView' => $listView,
        ]);
    }
}
