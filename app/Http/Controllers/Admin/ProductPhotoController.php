<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\AssignDepartmentPhotosAction;
use App\Domain\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Bulk product photos, one set per department.
 *
 * Separate screen rather than a control on the product list, because it is the only place in the
 * panel that writes to thousands of rows on one click. That deserves its own page with the rules
 * written down next to the button, not a menu item somebody hits by accident.
 */
final class ProductPhotoController
{
    public function __construct(private AssignDepartmentPhotosAction $photos) {}

    public function index(): View
    {
        $departments = Category::query()
            ->where('depth', 0)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('admin.pages.product-photos', [
            'departments' => $departments->map(fn (Category $department): array => [
                'model' => $department,
                'products' => $this->productCount($department),
                'photos' => $this->photos->currentPhotos($department),
                'own' => $this->photos->protectedCount($department),
            ]),
            'maxPhotos' => AssignDepartmentPhotosAction::MAX_PHOTOS,
            'totalProducts' => DB::table('products')->whereNull('deleted_at')->count(),
            'withOwnPhotos' => DB::table('product_images')
                ->where('path', 'like', AssignDepartmentPhotosAction::UPLOAD_PREFIX.'%')
                ->distinct()
                ->count('product_id'),
        ]);
    }

    /**
     * One set of photographs for the whole catalogue.
     *
     * Cruder than one per department — a bulb and a brake disc end up showing the same picture —
     * but it is one click instead of eight, which is what somebody setting up a demo wants. The
     * URLs are fetched once, not once per department.
     */
    public function storeAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'urls' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $result = $this->photos->assignEverywhere($this->urlsFrom($validated['urls']));
        } catch (RuntimeException $e) {
            return redirect()->route('admin.product-photos.index')->with('error', $e->getMessage());
        }

        $message = sprintf('%d photo%s applied to every product — %s in total.',
            $result['photos'], $result['photos'] === 1 ? '' : 's',
            number_format($result['products']));

        if ($result['protected'] > 0) {
            $message .= sprintf(' %d product%s left alone, %s photographs of %s own.',
                $result['protected'], $result['protected'] === 1 ? '' : 's',
                $result['protected'] === 1 ? 'it has' : 'they have',
                $result['protected'] === 1 ? 'its' : 'their');
        }

        return redirect()->route('admin.product-photos.index')->with('status', $message);
    }

    public function store(Request $request, string $department): RedirectResponse
    {
        $model = Category::query()->where('depth', 0)->findOrFail($department);

        $validated = $request->validate([
            'urls' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $result = $this->photos->assign($model, $this->urlsFrom($validated['urls']));
        } catch (RuntimeException $e) {
            return redirect()->route('admin.product-photos.index')->with('error', $e->getMessage());
        }

        $message = sprintf('%s: %d photo%s applied to %s product%s.',
            $model->name,
            $result['photos'], $result['photos'] === 1 ? '' : 's',
            number_format($result['products']), $result['products'] === 1 ? '' : 's');

        if ($result['protected'] > 0) {
            // Said out loud, because a bulk action that quietly skipped rows is indistinguishable
            // from one that failed on them.
            $message .= sprintf(' %d product%s left alone — %s photographs of %s own.',
                $result['protected'], $result['protected'] === 1 ? '' : 's',
                $result['protected'] === 1 ? 'it has' : 'they have',
                $result['protected'] === 1 ? 'its' : 'their');
        }

        return redirect()->route('admin.product-photos.index')->with('status', $message);
    }

    public function destroy(string $department): RedirectResponse
    {
        $model = Category::query()->where('depth', 0)->findOrFail($department);

        $removed = $this->photos->clear($model);

        return redirect()
            ->route('admin.product-photos.index')
            ->with('status', $removed === 0
                ? "{$model->name} had no bulk photos to remove."
                : "{$model->name}: {$removed} bulk photo rows removed. Products with photographs of "
                    .'their own are untouched.');
    }

    /**
     * Split a pasted block into URLs.
     *
     * Semicolons and newlines both, because somebody pasting four URLs will use whichever their
     * clipboard gave them. Commas are NOT a separator — they appear inside URLs.
     *
     * @return list<string>
     */
    private function urlsFrom(string $pasted): array
    {
        $urls = array_map('trim', preg_split('/[;\n\r]+/', $pasted) ?: []);

        return array_values(array_filter($urls, fn (string $url): bool => $url !== ''));
    }

    /** By path, because parts are filed against sub-categories, not the department itself. */
    private function productCount(Category $department): int
    {
        return DB::table('products as p')
            ->join('product_categories as pc', 'pc.product_id', '=', 'p.id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $department->path.'%')
            ->whereNull('p.deleted_at')
            ->distinct()
            ->count('p.id');
    }
}
