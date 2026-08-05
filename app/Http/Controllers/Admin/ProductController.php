<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Enums\ProductCondition;
use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\CatalogImport\Actions\RecordFieldOverridesAction;
use App\Domain\CatalogImport\Models\ProductFieldOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProductController
{
    public function __construct(private RecordFieldOverridesAction $recordOverrides) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.pages.products', [
            'search' => $search,
            'products' => Product::query()
                ->with('brand')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")))
                ->when($request->query('stock') === 'out', fn ($q) => $q->where('stock_status', 'out_of_stock'))
                ->when($request->query('stock') === 'low', fn ($q) => $q->where('stock_quantity', '<=', 5))
                ->orderByDesc('updated_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function edit(string $product): View
    {
        $model = Product::query()->with('brand')->findOrFail($product);

        return view('admin.pages.product-edit', [
            'product' => $model,
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'stockStatuses' => StockStatus::cases(),
            'conditions' => ProductCondition::cases(),
            // Which fields a human already owns — shown in the form so staff can see
            // what the importer will leave alone.
            'overridden' => ProductFieldOverride::lockedFieldsFor($model->id),
        ]);
    }

    public function update(Request $request, string $product): RedirectResponse
    {
        $model = Product::query()->findOrFail($product);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['nullable', 'string', 'exists:brands,id'],
            // numeric/integer rather than a hand-rolled regex — house rule, and
            // Laravel's rules handle the edge cases a regex forgets.
            'price_major' => ['required', 'numeric', 'min:0'],
            'sale_price_major' => ['nullable', 'numeric', 'min:0'],
            'stock_status' => ['required', 'string', 'in:in_stock,out_of_stock,on_backorder'],
            'condition' => ['required', 'string', 'in:new,refurbished,used'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $changes = [
            'name' => $validated['name'],
            'brand_id' => $validated['brand_id'] ?? null,
            'price_minor' => (int) round(((float) $validated['price_major']) * 100),
            'sale_price_minor' => $validated['sale_price_major'] === null
                ? null
                : (int) round(((float) $validated['sale_price_major']) * 100),
            'stock_status' => $validated['stock_status'],
            'condition' => $validated['condition'],
            'short_description' => $validated['short_description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        // Claim the changed fields for the human BEFORE writing, so the comparison is
        // against the stored values rather than the ones we just saved.
        $claimed = $this->recordOverrides->execute($model, $changes, $request->user()?->id);

        $model->update($changes);

        return redirect()
            ->route('admin.products.edit', $model->id)
            ->with('status', $claimed === []
                ? 'Saved. No field values changed, so nothing new was locked against imports.'
                : 'Saved. These fields are now yours and imports will not overwrite them: '.implode(', ', $claimed).'.');
    }

    /** Hand a field back to the importer. */
    public function releaseOverride(Request $request, string $product): RedirectResponse
    {
        $field = (string) $request->input('field');

        ProductFieldOverride::query()
            ->where('product_id', $product)
            ->where('field_name', $field)
            ->delete();

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', "'{$field}' was released — the next import may update it again.");
    }
}
