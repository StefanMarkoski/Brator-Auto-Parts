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

    public function test_every_carousel_card_is_marked_as_a_slide(): void
    {
        // No database needed: this reads the templates, because whether a card is wrong depends
        // on the markup it sits in, not on what the page happened to render.
        /*
         | Splide finds its slides by the class "splide__slide". The Best Seller strip on the
         | listing pages passed 'design-two' as the card variant — correct for the results grid
         | below it, which is NOT a carousel — and that overrode the card's default of
         | 'splide__slide design-two', leaving the slider with ZERO slides. Splide still mounted,
         | so with type:loop it computed a clone offset from nothing and shifted the list 286px
         | sideways: the strip sat visibly off to one side with cards at their natural 1642px.
         |
         | Checked in the MARKUP rather than the rendered page, because a card is only wrong when
         | it is inside a carousel and that context is what the template decides.
        */
        $offenders = [];

        foreach (glob(resource_path('views/shop/*.blade.php')) as $file) {
            $lines = explode("\n", (string) file_get_contents($file));

            foreach ($lines as $number => $line) {
                if (! str_contains($line, "@include('partials.product-card'")) {
                    continue;
                }

                /*
                 | Walk BACKWARDS to the nearest container marker rather than looking at a fixed
                 | window of preceding lines. The first version of this test looked back eight
                 | lines, and adding a comment above the include pushed splide__list out of range
                 | — so the test passed with the bug deliberately put back. Distance is not the
                 | question; which container encloses the card is.
                */
                $inCarousel = null;

                for ($i = $number - 1; $i >= 0; $i--) {
                    if (str_contains($lines[$i], 'splide__list')) {
                        $inCarousel = true;
                        break;
                    }

                    if (str_contains($lines[$i], 'product-list-items')) {
                        $inCarousel = false;
                        break;
                    }
                }

                if ($inCarousel !== true) {
                    continue;
                }

                // Inside a carousel, the card's own default keeps the slide class; an explicit
                // variant must not drop it.
                if (preg_match("/'variant'\s*=>\s*'([^']*)'/", $line, $variant)
                    && ! str_contains($variant[1], 'splide__slide')) {
                    $offenders[] = basename($file).':'.($number + 1)." variant='{$variant[1]}'";
                }
            }
        }

        $this->assertSame([], $offenders,
            'These cards sit inside a Splide carousel but are not marked as slides, so the '
            ."slider mounts with nothing in it and shifts sideways:\n  ".implode("\n  ", $offenders));
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

    public function test_every_department_icon_is_artwork_and_not_a_dimensions_placeholder(): void
    {
        $offenders = [];

        foreach (Category::query()->where('depth', 0)->get() as $category) {
            $file = public_path($category->image_path);

            if (! is_file($file)) {
                $offenders[] = "{$category->name}: {$category->image_path} does not exist";

                continue;
            }

            if ($this->isDimensionsPlaceholder($file)) {
                $offenders[] = "{$category->name}: {$category->image_path} is a grey placeholder";
            }
        }

        /*
         | Lighting and Interior showed "98X96" and "184X120" on the homepage — the theme's own
         | filler, because it ships six part icons and the shop has eight departments while the
         | seeder handed them out by counting. A 200 response proves nothing here: the <img> tag
         | was perfectly valid and pointed at a real file.
        */
        $this->assertSame([], $offenders,
            "Departments showing filler instead of an icon:\n  ".implode("\n  ", $offenders));
    }

    public function test_no_two_departments_share_an_icon(): void
    {
        $icons = Category::query()->where('depth', 0)->pluck('image_path', 'name');

        // The round-robin also meant a wheel on Engine and a battery on Wheels & Tires. Distinct
        // icons is the property that breaks the moment somebody goes back to counting.
        $this->assertCount($icons->count(), $icons->unique(),
            'Two departments share an icon: '.$icons->duplicates()->implode(', '));
    }

    /**
     * The theme's filler images are flat opaque grey with their own dimensions printed on them.
     *
     * Told apart from artwork by transparency, not by filename: every real icon here is line art
     * on a transparent background, and every placeholder is opaque edge to edge. That keeps the
     * check working for icons we draw ourselves as well as the theme's.
     */
    private function isDimensionsPlaceholder(string $file): bool
    {
        $image = imagecreatefrompng($file);
        $width = imagesx($image);
        $height = imagesy($image);
        $transparent = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 100) {
                    $transparent++;
                }
            }
        }

        return $transparent < ($width * $height) * 0.2;
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
