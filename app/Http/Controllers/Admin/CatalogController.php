<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\DeleteCategoryAction;
use App\Domain\Catalog\Actions\SaveBrandAction;
use App\Domain\Catalog\Actions\SaveCategoryAction;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\CatalogImport\Actions\PurgeImportSourceAction;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Domain\CatalogImport\Models\ImportSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/** Catalogue structure: the category tree, brands, and import history. */
final class CatalogController
{
    public function __construct(
        private SaveCategoryAction $saveCategory,
        private DeleteCategoryAction $deleteCategory,
        private SaveBrandAction $saveBrand,
    ) {}

    public function categories(): View
    {
        /*
         | Two different counts, because they answer two different questions.
         |
         | `subtree_products_count` is what a shopper sees: the department page resolves
         | everything beneath it by path prefix, so Braking shows 800 parts even though no
         | product is filed directly against Braking itself. Counting only the direct pivot
         | rows made every department read "0 parts" while its own page listed hundreds —
         | the same misleading-count bug the storefront review spent a day removing.
         |
         | `products_count` is the direct count, and that is the one the delete guard needs:
         | it is what would actually be orphaned.
        */
        $subtreeCount = DB::table('product_categories as pc')
            ->join('categories as descendant', 'descendant.id', '=', 'pc.category_id')
            ->whereColumn('descendant.path', 'like', DB::raw("CONCAT(categories.path, '%')"))
            ->selectRaw('COUNT(DISTINCT pc.product_id)');

        return view('admin.pages.categories', [
            'categories' => Category::query()
                ->with(['children' => fn ($q) => $q->withCount('products')])
                ->withCount('products')
                ->selectSub($subtreeCount, 'subtree_products_count')
                ->addSelect('categories.*')
                ->where('depth', 0)
                ->orderBy('position')
                ->get(),
            // Only departments can be a parent: the shop's navigation and the filter
            // sidebar are built for two levels, so a third would render nowhere.
            'parents' => Category::query()->where('depth', 0)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->categoryRules());

        $category = $this->saveCategory->create($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "{$category->name} was created at {$category->path}.");
    }

    public function updateCategory(Request $request, string $category): RedirectResponse
    {
        $model = Category::query()->findOrFail($category);
        $validated = $request->validate($this->categoryRules());

        try {
            $saved = $this->saveCategory->update($model, $validated);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.categories.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "{$saved->name} was saved. It now lives at {$saved->path}.");
    }

    public function destroyCategory(string $category): RedirectResponse
    {
        $model = Category::query()->findOrFail($category);

        try {
            $this->deleteCategory->execute($model);
        } catch (RuntimeException $e) {
            // The refusal Stefan asked for: say how many are in the way rather than
            // unlinking them and letting the products fall out of every listing.
            return redirect()->route('admin.categories.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "{$model->name} was deleted.");
    }

    public function brands(Request $request): View
    {
        return view('admin.pages.brands', [
            'brands' => Brand::query()->withCount('products')->orderBy('name')->paginate(30),
            'editing' => $request->query('edit') !== null
                ? Brand::query()->find($request->query('edit'))
                : null,
        ]);
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->brandRules());

        $brand = $this->saveBrand->create($validated);

        return redirect()->route('admin.brands.index')->with('status', "{$brand->name} was created.");
    }

    public function updateBrand(Request $request, string $brand): RedirectResponse
    {
        $model = Brand::query()->findOrFail($brand);
        $validated = $request->validate($this->brandRules());

        $saved = $this->saveBrand->update($model, $validated);

        return redirect()->route('admin.brands.index')->with('status', "{$saved->name} was saved.");
    }

    public function destroyBrand(string $brand): RedirectResponse
    {
        $model = Brand::query()->findOrFail($brand);

        try {
            $this->saveBrand->delete($model);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.brands.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.brands.index')->with('status', "{$model->name} was deleted.");
    }

    public function imports(PurgeImportSourceAction $purge): View
    {
        $sources = ImportSource::query()->orderBy('name')->get();

        return view('admin.pages.imports', [
            'sources' => $sources,
            'runs' => ImportRun::query()->with('source')->orderByDesc('created_at')->limit(20)->get(),
            // What erasing each supplier would take out, so the confirmation can state numbers
            // rather than ask staff to trust a button labelled "erase".
            'purgePreviews' => $sources->mapWithKeys(
                fn (ImportSource $source): array => [$source->id => $purge->preview($source)]
            ),
        ]);
    }

    /** @return array<string, list<string>> */
    private function categoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Uniqueness is not asserted here on purpose: the action derives a free slug
            // (braking-2, braking-3) rather than rejecting the form. Two departments can
            // legitimately want the same short name.
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, list<string>> */
    private function brandRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
