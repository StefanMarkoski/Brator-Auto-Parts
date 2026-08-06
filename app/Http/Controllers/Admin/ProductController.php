<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\AdjustStockAction;
use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Actions\DeleteProductAction;
use App\Domain\Catalog\Actions\SaveProductImagesAction;
use App\Domain\Catalog\Enums\ProductCondition;
use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\CatalogImport\Actions\RecordFieldOverridesAction;
use App\Domain\CatalogImport\Models\ProductFieldOverride;
use App\Support\Database\LikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

final class ProductController
{
    public function __construct(
        private RecordFieldOverridesAction $recordOverrides,
        private CreateProductAction $createProduct,
        private DeleteProductAction $deleteProduct,
        private AdjustStockAction $adjustStock,
        private SaveProductImagesAction $saveImages,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        // Deleted products are invisible by default, which means the only way back from a
        // mistaken delete would be a database client. This filter is the way back.
        $showDeleted = $request->query('status') === 'deleted';

        return view('admin.pages.products', [
            'search' => $search,
            'showDeleted' => $showDeleted,
            'deletedCount' => Product::onlyTrashed()->count(),
            'products' => ($showDeleted ? Product::onlyTrashed() : Product::query())
                ->with('brand')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', LikePattern::contains($search))
                    ->orWhere('sku', 'like', LikePattern::contains($search))))
                ->when($request->query('stock') === 'out', fn ($q) => $q->where('stock_status', 'out_of_stock'))
                ->when($request->query('stock') === 'low', fn ($q) => $q->where('stock_quantity', '<=', 5))
                ->orderByDesc('updated_at')
                // A tiebreaker, because updated_at alone is not unique: MySQL was free to
                // order ties differently per query, and page 1 and page 2 shared 24 of 25
                // SKUs. Any paginated list needs a deterministic total order.
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.product-create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->rules(),
            // Unique across trashed rows too: the index does not exempt soft-deleted
            // products, so allowing a reused SKU here trades a clear message for a
            // duplicate-key 500.
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'category_ids' => ['array'],
            'category_ids.*' => ['string', 'exists:categories,id'],
        ]);

        $product = $this->createProduct->execute(
            attributes: [
                ...$this->attributesFrom($validated, $request),
                'sku' => $validated['sku'],
                'slug' => $validated['slug'] ?? null,
                'stock_quantity' => (int) $validated['stock_quantity'],
            ],
            categoryIds: $validated['category_ids'] ?? [],
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('admin.products.edit', $product->id)
            ->with('status', "{$product->name} was created. It is "
                .($product->published_at === null
                    ? 'not published yet, so the shop will not show it until you publish it.'
                    : 'live in the shop.'));
    }

    public function edit(string $product): View
    {
        // withTrashed, so a deleted product still opens rather than 404ing — otherwise
        // the only way to restore one would be a database client.
        $model = Product::withTrashed()->with('brand')->findOrFail($product);

        return view('admin.pages.product-edit', [
            ...$this->formOptions(),
            'product' => $model,
            // Which fields a human already owns — shown in the form so staff can see
            // what the importer will leave alone.
            'overridden' => ProductFieldOverride::lockedFieldsFor($model->id),
            'selectedCategories' => $model->categories()->pluck('categories.id')->all(),
            'images' => $model->images()->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, string $product): RedirectResponse
    {
        $model = Product::withTrashed()->findOrFail($product);

        $validated = $request->validate([
            ...$this->rules(),
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'category_ids' => ['array'],
            'category_ids.*' => ['string', 'exists:categories,id'],
        ]);

        $changes = $this->attributesFrom($validated, $request);

        // Claim the changed fields for the human BEFORE writing, so the comparison is
        // against the stored values rather than the ones we just saved.
        $claimed = $this->recordOverrides->execute($model, $changes, $request->user()?->id);

        $model->update($changes);
        $model->categories()->sync($validated['category_ids'] ?? []);

        /*
         | Stock does NOT go into $changes. It is the one field on this form that is
         | ledgered, so writing it straight onto the row would leave stock_quantity saying
         | eleven while the movement history still adds up to eight — and the history is
         | the only thing that can explain a discrepancy later. The action derives the delta
         | and records it, under the same row lock a sale uses.
        */
        $delta = $this->adjustStock->execute(
            productId: $model->id,
            countedQuantity: (int) $validated['stock_quantity'],
            actorId: $request->user()?->id,
            note: 'Counted while editing the product.',
        );

        $message = $claimed === []
            ? 'Saved. No field values changed, so nothing new was locked against imports.'
            : 'Saved. These fields are now yours and imports will not overwrite them: '.implode(', ', $claimed).'.';

        if ($delta !== 0) {
            $message .= sprintf(' Stock %s by %d and the movement was recorded.',
                $delta > 0 ? 'rose' : 'fell', abs($delta));
        }

        return redirect()->route('admin.products.edit', $model->id)->with('status', $message);
    }

    public function destroy(string $product): RedirectResponse
    {
        $model = Product::query()->findOrFail($product);

        $this->deleteProduct->execute($model);

        return redirect()
            ->route('admin.products.index')
            ->with('status', "{$model->name} was removed from the shop. Past receipts still "
                .'show it, and you can restore it from the deleted list.');
    }

    public function restore(string $product): RedirectResponse
    {
        $model = Product::onlyTrashed()->findOrFail($product);

        $this->deleteProduct->restore($model);

        return redirect()
            ->route('admin.products.edit', $model->id)
            ->with('status', "{$model->name} was restored. It is still hidden from the shop "
                .'until you tick "Visible in the shop" and save.');
    }

    /**
     * The rules shared by create and edit. SKU and slug are not here: they are unique, and
     * the edit form has to exempt the row being edited while create must not.
     *
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['nullable', 'string', 'exists:brands,id'],
            // numeric/integer rather than a hand-rolled regex — house rule, and
            // Laravel's rules handle the edge cases a regex forgets.
            'price_major' => ['required', 'numeric', 'min:0'],
            'sale_price_major' => ['nullable', 'numeric', 'min:0', 'lte:price_major'],
            'stock_status' => ['required', 'string', 'in:in_stock,out_of_stock,on_backorder'],
            'condition' => ['required', 'string', 'in:new,refurbished,used'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Form input to column values. Shared so create and edit cannot disagree about what a
     * field means — the storefront reads one set of columns either way.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFrom(array $validated, Request $request): array
    {
        return [
            'name' => $validated['name'],
            'brand_id' => $validated['brand_id'] ?? null,
            'price_minor' => (int) round(((float) $validated['price_major']) * 100),
            // `nullable` means the key may be ABSENT, not merely null — reading it
            // directly threw "Undefined array key" and 500'd every save that omitted
            // the field. A test caught this only intermittently, because it depended on
            // whether the fixture product happened to have a sale price.
            'sale_price_minor' => ($validated['sale_price_major'] ?? null) === null
                ? null
                : (int) round(((float) $validated['sale_price_major']) * 100),
            'stock_status' => $validated['stock_status'],
            'condition' => $validated['condition'],
            'short_description' => $validated['short_description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            /*
             | published_at is what actually decides whether the shop shows a product, so
             | the form has to be able to set it. is_active alone gated nothing for a while,
             | which is how an unpublished product stayed purchasable.
             |
             | Publishing stamps now; unpublishing clears it. Re-publishing something that
             | already has a date keeps the original, so the "new arrivals" ordering does
             | not reshuffle every time someone opens and saves an old product.
            */
            'published_at' => $request->boolean('published')
                ? ($request->input('published_at_existing') ?: now())
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'stockStatuses' => StockStatus::cases(),
            'conditions' => ProductCondition::cases(),
            // Leaf categories only. A product sits in the sub-category a shopper filters
            // by; assigning it to a top-level department as well double-counts it in the
            // facets, since the category filter matches on the path prefix.
            'categories' => Category::query()
                ->whereDoesntHave('children')
                ->with('parent:id,name')
                ->orderBy('path')
                ->get(['id', 'name', 'parent_id', 'path']),
        ];
    }

    public function storeImages(Request $request, string $product): RedirectResponse
    {
        $model = Product::withTrashed()->findOrFail($product);

        $request->validate([
            'images' => ['required', 'array', 'max:8'],
            // `image` checks the actual file contents, not the extension — a .jpg that is
            // really a PHP script gets past a name check and lands in a web-served directory.
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [], ['images.*' => 'image']);

        /** @var list<UploadedFile> $files */
        $files = $request->file('images', []);

        $stored = $this->saveImages->upload($model, $files);

        return redirect()
            ->route('admin.products.edit', $model->id)
            ->with('status', $stored === 1
                ? 'One image was added.'
                : "{$stored} images were added.");
    }

    public function destroyImage(string $product, string $image): RedirectResponse
    {
        // Scoped to the product in the URL, not just looked up by id: without this, any
        // image id could be deleted through any product's route.
        $model = ProductImage::query()
            ->where('product_id', $product)
            ->findOrFail($image);

        $this->saveImages->delete($model);

        return redirect()->route('admin.products.edit', $product)->with('status', 'The image was removed.');
    }

    public function updateImage(Request $request, string $product, string $image): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:primary,up,down'],
        ]);

        $model = ProductImage::query()->where('product_id', $product)->findOrFail($image);

        if ($validated['action'] === 'primary') {
            $this->saveImages->makePrimary($model);
            $message = 'That image is now the main one.';
        } else {
            $this->saveImages->move($model, $validated['action']);
            $message = 'The image order was changed.';
        }

        return redirect()->route('admin.products.edit', $product)->with('status', $message);
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
