<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\ProductCollection;
use App\Domain\Content\Actions\SaveHomepageSectionAction;
use App\Domain\Content\Models\HomepageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The homepage editor.
 *
 * `homepage_sections` has existed since the schema was built, and the storefront has always
 * rendered from it — but nothing in the panel could touch it, so the "dynamic homepage" was
 * only dynamic if you edited the database by hand. This is the screen that was missing.
 */
final class HomepageController
{
    public function __construct(private SaveHomepageSectionAction $saveSection) {}

    public function index(): View
    {
        return view('admin.pages.homepage', [
            'sections' => HomepageSection::query()
                ->with('collection')
                ->orderBy('position')
                // A tiebreaker, because position is not unique by construction and MySQL is
                // otherwise free to order ties differently between requests — which would
                // make the up/down buttons appear to move the wrong row.
                ->orderBy('id')
                ->get(),
            'collections' => ProductCollection::query()->orderBy('name')->get(['id', 'name']),
            'collectionBacked' => SaveHomepageSectionAction::COLLECTION_BACKED,
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $model = HomepageSection::query()->findOrFail($section);

        $validated = $request->validate([
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'product_collection_id' => ['nullable', 'string', 'exists:product_collections,id'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        try {
            $this->saveSection->update($model, $validated);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.homepage.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.homepage.index')->with('status', 'The homepage was updated.');
    }

    public function move(Request $request, string $section): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
        ]);

        $model = HomepageSection::query()->findOrFail($section);

        $this->saveSection->move($model, $validated['direction']);

        return redirect()->route('admin.homepage.index')->with('status', 'The order was changed.');
    }
}
