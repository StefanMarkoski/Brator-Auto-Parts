<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Queries\Public\GetHomepageQuery;
use Illuminate\View\View;

final class HomeController
{
    public function __construct(private GetHomepageQuery $homepage) {}

    public function __invoke(): View
    {
        // Sections arrive already ordered and already resolved to their data, so the
        // view loops one list instead of the controller knowing ten section types.
        return view('home.index', $this->homepage->execute());
    }
}
