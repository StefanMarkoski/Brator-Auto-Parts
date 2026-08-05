<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Domain\CatalogImport\Models\ImportSource;
use Illuminate\View\View;

/** Read-only catalogue reference screens: categories, brands, import history. */
final class CatalogController
{
    public function categories(): View
    {
        return view('admin.pages.categories', [
            'categories' => Category::query()
                ->with('children')
                ->where('depth', 0)
                ->orderBy('position')
                ->get(),
        ]);
    }

    public function brands(): View
    {
        return view('admin.pages.brands', [
            'brands' => Brand::query()->withCount('products')->orderBy('name')->paginate(30),
        ]);
    }

    public function imports(): View
    {
        return view('admin.pages.imports', [
            'sources' => ImportSource::query()->orderBy('name')->get(),
            'runs' => ImportRun::query()->with('source')->orderByDesc('created_at')->limit(20)->get(),
        ]);
    }
}
