<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\Internal\FilteredProductsQuery;
use App\Domain\Fitment\Services\VehicleSelection;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filtering: categories, attributes, brand, price, rating and vehicle fitment.
 *
 * Each test compares the filtered result against the same question asked directly in
 * SQL, so a filter that silently does nothing fails instead of quietly returning
 * everything — which looks fine on screen and is the most likely way this breaks.
 */
final class ProductFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class, FitmentSeederSmall::class]);
    }

    public function test_a_category_filter_matches_what_sql_says(): void
    {
        $category = $this->populatedCategory();

        $expected = DB::table('products as p')
            ->join('product_categories as pc', 'pc.product_id', '=', 'p.id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $category->path.'%')
            ->where('p.is_active', true)
            ->distinct()->count('p.id');

        $this->assertSame($expected, $this->filtered()->count($this->filterFor($category)));
    }

    public function test_an_attribute_filter_matches_what_sql_says(): void
    {
        $category = $this->populatedCategory();

        $value = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->join('product_categories as pc', 'pc.product_id', '=', 'pav.product_id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $category->path.'%')
            ->where('a.code', 'origins')
            ->value('pav.value_string');

        // Compared against SQL rather than "must be fewer than before": with a small
        // seed a category can legitimately hold products that all share one value, and
        // a test that depends on the data shape fails for the wrong reason.
        $expected = DB::table('products as p')
            ->join('product_categories as pc', 'pc.product_id', '=', 'p.id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->join('product_attribute_values as pav', 'pav.product_id', '=', 'p.id')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('c.path', 'like', $category->path.'%')
            ->where('p.is_active', true)
            ->where('a.code', 'origins')
            ->where('pav.value_string', $value)
            ->distinct()->count('p.id');

        $filtered = $this->filtered()->count(
            $this->filterFor($category, ['attributes' => ['origins' => [$value]]])
        );

        $this->assertGreaterThan(0, $expected, 'The chosen value should match something.');
        $this->assertSame($expected, $filtered);

        // And a value that matches nothing must return nothing, not everything — the
        // failure mode where a broken filter silently passes the whole catalogue.
        $this->assertSame(0, $this->filtered()->count(
            $this->filterFor($category, ['attributes' => ['origins' => ['no-such-value']]])
        ));
    }

    public function test_two_attribute_groups_are_combined_with_and(): void
    {
        $category = $this->populatedCategory();

        $origin = DB::table('product_attribute_values as pav')->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('a.code', 'origins')->value('pav.value_string');
        $material = DB::table('product_attribute_values as pav')->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('a.code', 'materials')->value('pav.value_string');

        $one = $this->filtered()->count($this->filterFor($category, ['attributes' => ['origins' => [$origin]]]));
        $both = $this->filtered()->count($this->filterFor($category, [
            'attributes' => ['origins' => [$origin], 'materials' => [$material]],
        ]));

        // Separate groups narrow (AND); values inside one group widen (OR).
        $this->assertLessThanOrEqual($one, $both);
    }

    public function test_a_price_ceiling_excludes_dearer_parts(): void
    {
        $category = $this->populatedCategory();

        // The ceiling comes from the EFFECTIVE price — sale price where there is one —
        // because that is what the filter compares against and what the card displays.
        // Taking it from price_minor instead made this test disagree with the query.
        $ceiling = (int) DB::table('products as p')
            ->join('product_categories as pc', 'pc.product_id', '=', 'p.id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $category->path.'%')
            ->orderByRaw('COALESCE(p.sale_price_minor, p.price_minor)')
            ->value(DB::raw('COALESCE(p.sale_price_minor, p.price_minor)'));

        $filtered = $this->filtered()->page(
            $this->filterFor($category, ['priceMaxMinor' => $ceiling]),
            50
        );

        $this->assertNotEmpty($filtered, 'The cheapest part must survive its own price ceiling.');

        foreach ($filtered as $card) {
            $this->assertLessThanOrEqual($ceiling, $card->price->minor);
        }

        // And the ceiling genuinely excludes, as long as the category holds more than
        // one distinct price — with a tiny seed it may not.
        $total = $this->filtered()->count($this->filterFor($category));
        $capped = $this->filtered()->count($this->filterFor($category, ['priceMaxMinor' => $ceiling]));

        $this->assertLessThanOrEqual($total, $capped);

        if ($total > 1) {
            $this->assertLessThan($total, $capped,
                'A ceiling at the cheapest price must exclude the dearer parts.');
        }
    }

    public function test_the_vehicle_filter_returns_only_parts_that_fit(): void
    {
        $variantId = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        $expected = DB::table('product_vehicle_fitments as f')
            ->join('products as p', 'p.id', '=', 'f.product_id')
            ->where('f.vehicle_variant_id', $variantId)
            ->where('p.is_active', true)
            ->distinct()->count('p.id');

        $filtered = $this->filtered()->count(ProductFilter::fromArray(['vehicleVariantId' => $variantId]));

        $this->assertSame($expected, $filtered);
        $this->assertGreaterThan(0, $filtered);
    }

    public function test_choosing_a_vehicle_narrows_the_listing_and_clearing_restores_it(): void
    {
        $category = $this->populatedCategory();
        // Pick a variant that fits SOME but not all of the category — with a small seed
        // a randomly chosen variant can happen to fit everything, and then "narrowing"
        // legitimately changes nothing and the test fails for the wrong reason.
        $inCategory = DB::table('product_categories as pc')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $category->path.'%')
            ->distinct()->pluck('pc.product_id');

        $variantId = (int) DB::table('product_vehicle_fitments')
            ->whereIn('product_id', $inCategory)
            ->select('vehicle_variant_id', DB::raw('COUNT(*) as fits'))
            ->groupBy('vehicle_variant_id')
            ->havingRaw('COUNT(*) < ?', [$inCategory->count()])
            ->orderByDesc('fits')
            ->value('vehicle_variant_id');

        if ($variantId === 0) {
            $this->markTestSkipped('This seed gave no variant that fits only part of the category.');
        }

        $all = $this->cardCount($this->get(route('shop.category', $category->slug))->getContent());

        session([VehicleSelection::SESSION_KEY => $variantId]);
        $narrowed = $this->cardCount($this->get(route('shop.category', $category->slug))->getContent());

        $this->assertLessThan($all, $narrowed, 'Choosing a vehicle should narrow the listing.');

        // The vehicle is a filter, not a gate — clearing must restore everything.
        $this->post(route('vehicle.clear'));
        $restored = $this->cardCount($this->get(route('shop.category', $category->slug))->getContent());

        $this->assertSame($all, $restored);
    }

    public function test_browsing_without_a_vehicle_shows_the_whole_catalogue(): void
    {
        $category = $this->populatedCategory();

        // Stefan was explicit: the vehicle filter must never be compulsory.
        $this->get(route('shop.category', $category->slug))
            ->assertOk()
            ->assertSee('Showing parts for every vehicle', false);
    }

    public function test_facet_counts_ignore_their_own_group(): void
    {
        $category = $this->populatedCategory();
        $codes = ['origins', 'materials'];

        $value = DB::table('product_attribute_values as pav')->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('a.code', 'origins')->value('pav.value_string');

        $facets = $this->filtered()->facets(
            $this->filterFor($category, ['attributes' => ['origins' => [$value]]]),
            $codes
        );

        // With "origins" ticked, the OTHER origins options must still show real counts —
        // otherwise a shopper can never switch their choice, only clear it.
        $this->assertGreaterThan(0, array_sum($facets['attributes']['origins']));
    }

    public function test_the_sidebar_keeps_its_checked_state(): void
    {
        $category = $this->populatedCategory();
        $value = DB::table('product_attribute_values as pav')->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('a.code', 'origins')->value('pav.value_string');

        $html = $this->get(route('shop.category', $category->slug).'?attr[origins][]='.urlencode($value))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="attr\[origins\]\[\]"\s+value="'.preg_quote($value, '/').'"[^>]*checked/',
            $html,
            'A filter the shopper selected must come back ticked.'
        );
    }

    public function test_sorting_by_price_actually_sorts(): void
    {
        $category = $this->populatedCategory();

        $cards = $this->filtered()->page($this->filterFor($category, ['sort' => 'price_asc']), 20);
        $prices = $cards->map(fn ($c) => $c->price->minor)->all();
        $sorted = $prices;
        sort($sorted);

        $this->assertSame($sorted, $prices);
    }

    public function test_the_listing_marks_the_regions_the_in_place_filter_swaps(): void
    {
        $category = $this->populatedCategory();

        $html = $this->get('/shop/'.$category->slug)->assertOk()->getContent();

        /*
         | Filtering no longer reloads the page — storefront.js fetches the same URL and puts
         | these regions in place, which is what keeps the shopper's scroll position. It finds
         | them by these attributes and falls back to a full navigation if any is missing, so
         | losing one degrades silently back to jumping to the top. Named here so that is a
         | failing test rather than something nobody notices.
        */
        foreach (['data-listing-filters', 'data-listing-summary', 'data-listing-grid'] as $hook) {
            $this->assertStringContainsString($hook, $html,
                "The listing no longer marks '{$hook}', so filtering will fall back to reloading.");
        }
    }

    public function test_both_the_grid_and_list_views_mark_the_same_regions(): void
    {
        $category = $this->populatedCategory();

        $grid = $this->get('/shop/'.$category->slug)->assertOk()->getContent();
        $list = $this->get('/shop/'.$category->slug.'?view=list')->assertOk()->getContent();

        // Two near-identical templates, and the view toggle swaps between them in place. A
        // hook added to one and forgotten in the other breaks filtering in exactly one view
        // — the kind of thing a customer finds rather than we do.
        foreach (['data-listing-filters', 'data-listing-summary', 'data-listing-grid'] as $hook) {
            $this->assertStringContainsString($hook, $grid, "The grid view is missing '{$hook}'.");
            $this->assertStringContainsString($hook, $list, "The list view is missing '{$hook}'.");
        }
    }

    public function test_the_price_slider_is_marked_so_the_sidebar_sync_leaves_it_alone(): void
    {
        $category = $this->populatedCategory();

        /*
         | The in-place update patches every filter group EXCEPT the one holding this
         | attribute, because noUiSlider throws "Slider was already initialized" if its markup
         | is replaced under it. Without the marker the sync would reach the slider and the
         | price filter would die the first time any other filter was ticked.
        */
        $this->get('/shop/'.$category->slug)->assertOk()->assertSee('data-price-slider', false);
    }

    private function filtered(): FilteredProductsQuery
    {
        return app(FilteredProductsQuery::class);
    }

    private function populatedCategory(): Category
    {
        return Category::query()->where('depth', 1)->whereHas('products')->firstOrFail();
    }

    /** @param  array<string, mixed>  $overrides */
    private function filterFor(Category $category, array $overrides = []): ProductFilter
    {
        return ProductFilter::fromArray([
            'categoryPath' => $category->path,
            'categorySlug' => $category->slug,
            ...$overrides,
        ]);
    }

    private function cardCount(string $html): int
    {
        return preg_match_all('/brator-product-single-item-area design-two/', $html);
    }
}
