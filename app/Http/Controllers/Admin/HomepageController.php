<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\ProductCollection;
use App\Domain\Content\Actions\ImportHeroImageAction;
use App\Domain\Content\Actions\SaveHomepageSectionAction;
use App\Domain\Content\Actions\SaveWhatsHotBoxAction;
use App\Domain\Content\Models\Banner;
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
            // Which sections actually print a heading / subheading. The editor offered both to
            // every section and four of them printed neither, so the box took the text, went
            // green, and changed nothing on the shop.
            'headingBacked' => SaveHomepageSectionAction::HEADING_BACKED,
            'subheadingBacked' => SaveHomepageSectionAction::SUBHEADING_BACKED,
            // Every hero picture, not only the live ones, so a switched-off or scheduled image
            // is still visible to whoever manages them rather than silently absent.
            'heroImages' => Banner::query()
                ->where('placement', ImportHeroImageAction::PLACEMENT)
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
            'comfortableWidth' => ImportHeroImageAction::COMFORTABLE_WIDTH,
            // The What's Hot promo boxes, and the categories a box may point at. Every link is
            // chosen from this list rather than typed, so a box cannot advertise a department the
            // shop does not have.
            'whatsHot' => Banner::query()
                ->where('placement', SaveWhatsHotBoxAction::PLACEMENT)
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
            'linkableCategories' => Category::query()
                ->where('is_active', true)
                ->orderBy('depth')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'depth']),
            'whatsHotVisible' => SaveWhatsHotBoxAction::VISIBLE_AT_ONCE,
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
