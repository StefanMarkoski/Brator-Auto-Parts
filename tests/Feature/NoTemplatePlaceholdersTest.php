<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No page may show the theme's demo content as if it were ours.
 *
 * Stefan found this the way it is always found — by looking at the site and seeing a
 * mini-cart claiming four wheels he had never added, a listing announcing "1 - 40 of
 * 1,652 results", a spec table crediting "SpareGold", and a wall of "Accura" makes.
 * Every one of those pages returned 200, so nothing in the suite objected.
 *
 * These are the theme author's own strings and prices. If any reappears, a block has
 * been left hardcoded — or a new one was pasted in from the template.
 */
final class NoTemplatePlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    /** Demo product names, brands, makes and counts from the purchased template. */
    private const PLACEHOLDERS = [
        'Silver with Mirror Cut',
        'Automatic Proshift',
        'Evolution Sport Drilled',
        'Helix Engine Fluids',
        'Customwheels',
        'SpareGold',
        'Brakepro',
        'Wruth',
        'Accura',
        'Mercedes GLC',
        '1,652 results',
        '2,360 Sold',
        'Make 01',
        'Model 01',
        'Sub Model 01',
        'Best Match 2',
        '19” DIAMETER',

        /*
         | THE FOOTER, found the same way — by looking at it. It had been left untouched while
         | the rest of the theme's fiction was stripped, so the homepage still carried:
         |
         | CARiD.com   a real competitor, named in the legal disclaimer as the seller of
         |             everything in this shop. The template was built from their site.
         | #1 US's     a claim to be the biggest US marketplace, by a single-seller shop in
         |             North Macedonia.
         | Investors / Career / Affiliate Program / Parnership
         |             pages this business does not have, all pointed at the contact form —
         |             a redirect standing in for a lie, plus the theme's own typo.
         | Brator Inc. the company name as a dead link.
         | 2022        a hardcoded year, wrong in the footer of a live shop.
        */
        'CARiD',
        "#1 US's",
        'Investors',
        'Affiliate Program',
        'Parnership',
        'Brator Inc.',
        '© 2022',

        // The theme's broken English, in copy a shopper reads.
        'not spam',
        'accepted the our',
    ];

    public function test_no_storefront_page_shows_the_themes_demo_content(): void
    {
        $offenders = [];

        foreach ($this->pages() as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach (self::PLACEHOLDERS as $placeholder) {
                if (str_contains($html, $placeholder)) {
                    $offenders[] = "{$page} → '{$placeholder}'";
                }
            }
        }

        $this->assertSame([], $offenders,
            "Template demo content is being shown as if it were real data:\n  "
            .implode("\n  ", $offenders));
    }

    public function test_the_footer_states_the_current_year(): void
    {
        foreach (['/', '/shop'] as $page) {
            $this->get($page)->assertOk()->assertSee('© '.now()->year, false);
        }
    }

    public function test_the_footer_columns_add_up_to_a_full_row(): void
    {
        /*
         | Both footers laid their columns out to ELEVEN of twelve — one of them by way of an
         | empty spacer div — so the right-hand column stopped short of the container edge and
         | the four headings sat at four unrelated widths. Counted rather than eyeballed,
         | because a row that does not add to twelve is the specific defect, and it is invisible
         | until somebody looks at the footer on a wide screen.
        */
        foreach ([
            'resources/views/partials/footer-top.blade.php',
            'resources/views/partials/footer-shop.blade.php',
        ] as $file) {
            $markup = (string) file_get_contents(base_path($file));

            // Only the first row of each — the bottom bars are their own row of thirds.
            preg_match_all('/class="col-xl-(\d+)/', $markup, $matches);

            $units = array_map('intval', array_slice($matches[1], 0, 4));

            $this->assertSame(12, array_sum($units),
                "{$file}: its xl columns add to ".array_sum($units).' of 12, so one edge of the '
                .'footer will not line up with the rest of the page.');
        }
    }

    public function test_no_footer_link_goes_nowhere(): void
    {
        foreach (['/', '/shop'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $footer = substr($html, (int) strpos($html, 'brator-footer-top-area') ?: 0);

            // Placeholder anchors: the theme's way of shipping a link it had no page for.
            foreach (['href="#_"', 'href="#-"', 'href="#"'] as $dead) {
                $this->assertStringNotContainsString($dead, $footer,
                    "{$page}: the footer has a link that goes nowhere ({$dead}).");
            }
        }
    }

    public function test_configured_contact_details_are_shown_and_dialable(): void
    {
        // Set on the config rather than read from .env, so this proves the WIRING and cannot
        // pass or fail depending on whose machine it runs on.
        config([
            'shop.contact.phone' => '071234567',
            'shop.contact.address' => '1420 Woodward Ave, Detroit, MI 48226',
        ]);

        $html = $this->get('/shop')->assertOk()->getContent();

        $this->assertStringContainsString('071234567', $html);
        $this->assertStringContainsString('1420 Woodward Ave, Detroit, MI 48226', $html);

        // A number a shopper cannot tap on a phone is half a phone number, and the leading
        // zero has to survive into the href.
        $this->assertStringContainsString('href="tel:071234567"', $html);
    }

    public function test_a_contact_line_with_no_value_is_omitted_not_faked(): void
    {
        config(['shop.contact.phone' => null, 'shop.contact.address' => null]);

        $html = $this->get('/shop')->assertOk()->getContent();

        /*
         | The rule the theme broke: it shipped "1800 500 1234 925" and an Asheville address as
         | this shop's own details. An empty value has to produce nothing at all — not a bare
         | "Call us" label with no number after it, and certainly not a placeholder.
        */
        $this->assertStringNotContainsString('tel:', $html);
        $this->assertStringNotContainsString('Call us', $html);
    }

    public function test_no_page_shows_prices_in_dollars(): void
    {
        // The shop trades in denars. A dollar sign means a hardcoded theme price
        // survived — the Money value object never renders one.
        $offenders = [];

        foreach ($this->pages() as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            if (preg_match_all('/\$[0-9][0-9.,]*/', $html, $matches)) {
                $offenders[] = "{$page} → ".implode(', ', array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offenders,
            "Hardcoded dollar prices from the theme:\n  ".implode("\n  ", $offenders));
    }

    public function test_the_listing_reports_the_real_number_of_products(): void
    {
        $category = Category::query()->where('depth', 1)->whereHas('products')->firstOrFail();

        $expected = Product::query()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
            ->where('is_active', true)
            ->count();

        $this->get(route('shop.category', $category->slug))
            ->assertOk()
            // The theme hardcoded "of 1,652 results" on every listing.
            ->assertSee('of '.number_format($expected).' result', false);
    }

    public function test_the_mini_cart_reflects_the_real_basket(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The theme shipped two wheels and a "$838.19" total in here, so a first-time
        // visitor was told they had a basket.
        $this->assertStringContainsString('Your cart is empty', $html);

        $product = Product::query()->where('stock_status', 'in_stock')->firstOrFail();
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);

        $this->get('/')->assertOk()->assertSee($product->name);
    }

    public function test_the_product_page_specification_table_shows_that_products_attributes(): void
    {
        $product = Product::query()->with('brand')->firstOrFail();

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee($product->sku)
            // Real attribute labels from category_attributes, not the theme's wheel specs.
            ->assertSee('Origins', false)
            ->assertSee('Materials', false);
    }

    /** @return list<string> */
    private function pages(): array
    {
        $category = Category::query()->where('depth', 1)->whereHas('products')->firstOrFail();
        $product = Product::query()->firstOrFail();

        return [
            '/',
            '/shop',
            route('shop.category', $category->slug),
            route('shop.category', $category->slug).'?view=list',
            route('shop.product', $product->slug),
            '/cart',
            '/about',
            '/contact',
            '/search?s=brake',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CatalogStructureSeeder::class,
            ProductSeederSmall::class,
            FitmentSeederSmall::class,
            MerchandisingSeeder::class,
            HomepageSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
