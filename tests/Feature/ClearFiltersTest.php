<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Fitment\Services\VehicleSelection;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Clear all filters" — the last open finding from the frontend review (F-3).
 *
 * The reviewer said it unticked the checkboxes but left the results filtered. My first
 * inspection found a plain link to the bare category URL and I reported it as not
 * reproducing. That was wrong, and the reason is worth writing down: EVERY OTHER FILTER
 * LIVES IN THE URL, BUT THE VEHICLE LIVES IN THE SESSION. A link to the bare URL therefore
 * clears everything visible in the sidebar and silently keeps the car — so the boxes come
 * back empty while the result count stays narrowed, and the link itself keeps showing,
 * making it look like clicking did nothing at all.
 *
 * Reading the markup could not show that. Only asking what a shopper actually sees could.
 */
final class ClearFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class, FitmentSeeder::class]);
    }

    public function test_clearing_filters_also_clears_the_chosen_vehicle(): void
    {
        $variant = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        // A car is chosen, so the listing is narrowed by something that is not in the URL.
        $this->withSession([VehicleSelection::SESSION_KEY => $variant]);

        $narrowed = $this->countOn('/shop/braking?rating=4');

        // Clear everything.
        $this->post('/filters/clear', ['redirect_to' => '/shop/braking'])
            ->assertRedirect('/shop/braking');

        $this->assertNull(session(VehicleSelection::SESSION_KEY),
            'Clearing all filters left the chosen vehicle in the session, so the results stay '
            .'narrowed while the sidebar shows nothing ticked.');

        $cleared = $this->countOn('/shop/braking');

        $this->assertGreaterThan($narrowed, $cleared,
            'Clearing filters did not widen the results.');
    }

    public function test_the_clear_link_disappears_once_there_is_nothing_to_clear(): void
    {
        $variant = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        $this->withSession([VehicleSelection::SESSION_KEY => $variant])
            ->get('/shop/braking')
            ->assertOk()
            ->assertSee('Clear all filters', false);

        $this->post('/filters/clear', ['redirect_to' => '/shop/braking']);

        // The tell that the old version was broken: the control stayed on screen after being
        // used, because the thing keeping it there was never cleared.
        $this->get('/shop/braking')
            ->assertOk()
            ->assertDontSee('Clear all filters', false);
    }

    public function test_clearing_filters_keeps_you_on_the_same_listing(): void
    {
        $this->withSession(['_previous' => ['url' => 'http://localhost/shop/braking']]);

        $this->post('/filters/clear', ['redirect_to' => '/shop/braking'])
            ->assertRedirect('/shop/braking');

        // Not the homepage. Dumping a shopper back to the front page to undo one filter is
        // its own kind of dead end.
        $this->get('/shop/braking')->assertOk()->assertSee('Braking', false);
    }

    public function test_a_redirect_target_off_this_site_is_refused(): void
    {
        // redirect_to comes from the page, so it is user input. Reflecting it unchecked turns
        // this endpoint into an open redirect — a link that looks like the shop and lands on
        // somebody else's site.
        $this->post('/filters/clear', ['redirect_to' => 'https://evil.example.com/phish'])
            ->assertRedirect('/shop');
    }

    public function test_clearing_filters_does_not_empty_the_basket(): void
    {
        // "Filters" must mean filters. The vehicle and the basket both live in the session,
        // and clearing the wrong one loses somebody's shopping.
        $product = Product::query()->visible()->firstOrFail();

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);
        $this->post('/filters/clear', ['redirect_to' => '/shop']);

        $this->get('/cart')->assertOk()->assertSee($product->name, false);
    }

    public function test_the_listing_page_has_no_nested_forms(): void
    {
        $variant = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        $html = $this->withSession([VehicleSelection::SESSION_KEY => $variant])
            ->get('/shop/braking?rating=4')
            ->assertOk()
            ->getContent();

        /*
         | THE GAP THAT LET THE REAL BUG THROUGH.
         |
         | Every other test here posts straight to /filters/clear, so they all passed while the
         | on-page control did nothing useful: the clear form was nested inside the filter
         | form, which is invalid HTML, so the browser dropped the inner one and the button
         | submitted the OUTER form as a GET — the page came back with the CSRF token in the
         | query string and the car still selected.
         |
         | Testing an endpoint is not testing a control. This walks the markup instead.
        */
        $depth = 0;

        foreach (preg_split('#(?=<form\b)|(?<=</form>)#i', $html) as $chunk) {
            if (preg_match('#^<form\b#i', $chunk)) {
                $depth++;

                $this->assertLessThanOrEqual(1, $depth,
                    'A form is nested inside another form. The browser silently drops the inner '
                    .'one, so its submit button posts the outer form instead.');
            }

            if (preg_match('#</form>$#i', $chunk)) {
                $depth--;
            }
        }
    }

    public function test_the_clear_button_targets_the_clear_form(): void
    {
        $variant = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        $html = $this->withSession([VehicleSelection::SESSION_KEY => $variant])
            ->get('/shop/braking?rating=4')
            ->assertOk()
            ->getContent();

        // The button reaches its form by id, so both halves have to be present or the control
        // is inert in a way that looks fine in the markup.
        $this->assertStringContainsString('id="clear-filters"', $html);
        $this->assertStringContainsString('form="clear-filters"', $html);
        $this->assertStringContainsString(route('filters.clear', [], false), $html);
    }

    public function test_the_engine_box_remembers_the_chosen_vehicle(): void
    {
        $variant = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');
        $row = DB::table('vehicle_variants')->where('id', $variant)->first();

        // The whole cascade, as the session would hold it after a shopper picked a car.
        $html = $this->withSession([
            VehicleSelection::SESSION_KEY => $variant,
            VehicleSelection::PICKER_KEY => [
                'year' => $row->year_from,
                'make' => (int) DB::table('vehicle_models')->where('id', $row->model_id)->value('make_id'),
                'model' => (int) $row->model_id,
                'name' => $row->name,
            ],
        ])->get('/shop')->assertOk()->getContent();

        /*
         | The Engine <option> had no @selected, so this one level of the picker came back
         | empty on every real page load even though a vehicle WAS chosen — the shopper saw
         | Year, Make, Model and Sub Model filled in and Engine blank, which reads as the
         | form having lost their car.
        */
        $this->assertMatchesRegularExpression(
            '/<option value="'.$variant.'"\s+selected/',
            $html,
            'The Engine dropdown does not mark the chosen vehicle as selected.'
        );
    }

    public function test_the_picker_marks_the_regions_the_cascade_updates(): void
    {
        $html = $this->get('/shop')->assertOk()->getContent();

        /*
         | Choosing a Year no longer reloads the page: storefront.js posts the same form and
         | copies the new options into the dropdowns already on screen, which is what stops
         | the homepage jumping back to the top five times on the way to one car. It finds
         | the form and the "Start again" area by these attributes and falls back to a full
         | reload without them.
        */
        foreach (['data-vehicle-picker', 'data-vehicle-extras'] as $hook) {
            $this->assertStringContainsString($hook, $html,
                "The vehicle picker no longer marks '{$hook}', so its cascade will reload the page.");
        }
    }

    public function test_start_again_sits_inside_the_search_box_where_the_theme_styles_it(): void
    {
        $html = $this->get('/shop')->assertOk()->getContent();

        /*
         | IT HAS TO BE INSIDE THE PICKER FORM, and that is the whole point of the change.
         | The theme styles buttons per section — .brator-parts-search-box-area.design-two
         | ... form button — so the same button in its own little form under the box got the
         | browser's default grey, sitting on top of the hero image. Nothing about it looked
         | broken to a test: it rendered, it worked, it was simply unstyled.
        */
        $this->assertStringContainsString('data-vehicle-reset', $html, 'The "Start again" button is gone.');
        $this->assertStringContainsString('data-vehicle-reset', $this->pickerForm($html),
            'The "Start again" button is outside the search box, so the theme gives it no styling.');

        // And it is a button in THAT form via formaction, not a second form: forms cannot nest,
        // so a nested one would be reparented by the browser and the button would sit outside
        // the box again on screen while still looking correct in the source.
        $this->assertSame(0, preg_match_all('/<form[^>]+action="[^"]*vehicle\/clear/', $html),
            'A separate form posts to the clear route, which cannot live inside the search box.');
    }

    public function test_start_again_appears_only_once_there_is_something_to_clear(): void
    {
        // Nothing picked: rendered but hidden with the theme's own utility class, because the
        // in-place cascade only copies <option>s and cannot conjure a button that is not there.
        $this->assertMatchesRegularExpression('/class="d-none"[^>]*data-vehicle-reset/',
            $this->pickerForm($this->get('/shop')->assertOk()->getContent()),
            'With no vehicle chosen, "Start again" should be hidden rather than absent.');

        $html = $this->withSession([VehicleSelection::PICKER_KEY => ['year' => 2019]])
            ->get('/shop')->assertOk()->getContent();

        $this->assertStringNotContainsString('d-none', $this->pickerForm($html),
            'A year is chosen, so "Start again" should be showing.');
    }

    public function test_start_again_returns_to_the_page_it_was_clicked_on(): void
    {
        /*
         | Both fields are posted now, because the button submits the picker's own form:
         | redirect_to means "where Search goes" and is always the listing. Honouring that
         | here threw a shopper clearing the box on the homepage into the catalogue — which
         | is what the old button did, since it posted the ABSOLUTE url and SafeRedirect
         | refuses anything with a host, falling back to /shop without a word.
        */
        $this->post(route('vehicle.clear'), [
            'reset_redirect_to' => '/shop/braking?rating=4',
            'redirect_to' => '/shop',
        ])->assertRedirect('/shop/braking?rating=4');
    }

    public function test_start_again_cannot_be_used_to_send_a_shopper_off_site(): void
    {
        // Still user input, so still through SafeRedirect.
        $this->post(route('vehicle.clear'), [
            'reset_redirect_to' => 'https://evil.example.com/login',
        ])->assertRedirect('/shop');
    }

    /** The picker form's own markup, so "inside the search box" can actually be asserted. */
    private function pickerForm(string $html): string
    {
        preg_match('/<form[^>]*data-vehicle-picker.*?<\/form>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'No vehicle picker form on the page.');

        return $matches[0];
    }

    public function test_no_blade_directive_leaks_onto_the_listing_page(): void
    {
        $variant = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        $html = $this->withSession([VehicleSelection::SESSION_KEY => $variant])
            ->get('/shop/braking?rating=4')
            ->assertOk()
            ->getContent();

        /*
         | This caught a real one. The clear-filters label was written as
         | "Clear all filters@if (…), including your car@endif" and rendered VERBATIM, @if and
         | all, because Blade treats an @ directly preceded by a word character as part of an
         | email address and leaves the directive uncompiled. The markup reads perfectly
         | correctly; only the output shows it.
        */
        foreach (['@if', '@endif', '@foreach', '@php', '@endphp', '@include'] as $directive) {
            $this->assertStringNotContainsString($directive, $html,
                "An uncompiled Blade directive ({$directive}) is being shown to shoppers.");
        }
    }

    /**
     * How many distinct products the listing actually shows.
     *
     * Counts product links rather than parsing the "1 - 12 of 43 results" prose. The first
     * version scraped that sentence and broke as soon as a filter combination returned
     * nothing and the page said something else instead — measuring the words rather than the
     * thing they describe.
     */
    private function countOn(string $url): int
    {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match_all('#href="/product/([a-z0-9-]+)"#', $html, $m);

        return count(array_unique($m[1]));
    }
}
