<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The site has to be navigable, not just a set of reachable URLs.
 *
 * This exists because the nav was shipped dead: pages were built and linked from
 * nowhere, and the theme's own menu still pointed at "Home 1 / Home 2 / Blog Single /
 * Coming Soon" — a template showcase, not a shop. Every page returned 200, so nothing
 * caught it. This crawls the links a visitor can actually click and follows them.
 */
final class NavigationFlowTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Follow every internal link on every main page. One broken destination anywhere
     * in the navigation fails this.
     */
    public function test_every_internal_link_on_every_page_resolves(): void
    {
        $pages = ['/', '/shop', '/cart', '/about', '/contact', '/search?s=brake'];
        $checked = [];
        $broken = [];

        foreach ($pages as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach ($this->internalLinks($html) as $link) {
                if (isset($checked[$link])) {
                    continue;
                }
                $checked[$link] = true;

                $response = $this->get($link);

                // A redirect is not a dead link — some links deliberately set state and
                // send the shopper on (picking a make seeds the vehicle picker). Follow it
                // and judge where it lands.
                if ($response->isRedirect()) {
                    $target = $response->headers->get('Location');
                    $response = $this->get($target);

                    if ($response->getStatusCode() !== 200) {
                        $broken[] = "{$link} -> {$target} (status {$response->getStatusCode()}, linked from {$page})";
                    }

                    continue;
                }

                if ($response->getStatusCode() !== 200) {
                    $broken[] = "{$link} (status {$response->getStatusCode()}, linked from {$page})";
                }
            }
        }

        $this->assertSame([], $broken,
            "Links a visitor can click that do not resolve:\n  ".implode("\n  ", $broken));

        // Guard against the opposite failure: a nav that resolves because it links
        // to nothing. The homepage alone should reach categories and products.
        $this->assertGreaterThan(10, count($checked),
            'Suspiciously few internal links — the navigation is probably dead again.');
    }

    public function test_the_navigation_offers_the_real_shop_pages_not_the_theme_demo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The theme's showcase menu must be gone.
        foreach (['Home 1', 'Home 2', 'Home 3', 'Blog Single', 'Coming Soon',
            'Product Layout 1', 'Product Compare'] as $demoEntry) {
            $this->assertStringNotContainsString($demoEntry, $html,
                "The theme's demo menu entry '{$demoEntry}' is still in the navigation.");
        }

        // And real destinations must be present.
        $this->assertStringContainsString('href="/about"', $html);
        $this->assertStringContainsString('href="/contact"', $html);
        $this->assertStringContainsString('href="/shop"', $html);
    }

    public function test_the_mega_menu_lists_real_categories(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="/shop/braking"', $html);
        $this->assertStringContainsString('Braking', $html);
    }

    public function test_no_page_links_to_a_theme_html_file(): void
    {
        foreach (['/', '/shop', '/cart', '/about', '/contact'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            preg_match_all('/href="([a-z0-9-]+\.html[^"]*)"/', $html, $m);

            $this->assertSame([], array_unique($m[1]),
                "{$page} still links to theme .html files, which 404: "
                .implode(', ', array_unique($m[1])));
        }
    }

    public function test_the_main_menu_row_fills_its_twelve_columns(): void
    {
        $markup = (string) file_get_contents(base_path('resources/views/partials/header.blade.php'));

        /*
         | The main menu's labels need about 737px on one line. The theme gave its column 5 of
         | twelve and spent 4 more on a "Recently Viewed" link that pointed nowhere — so the menu
         | got 609px, and because its <ul> is flex with nowrap the items could not spill out of
         | the row: they shrank, and the text wrapped instead. "Auto Parts", "Wheels & Tires",
         | "About us" and "Contact Us" each broke over two lines on a full-width screen.
         |
         | Counted rather than eyeballed, because a row that does not add to twelve is the actual
         | defect, and it only shows above 1600px — which is not the width anyone tests at.
        */
        // Bounded by the next block rather than a character count: the first column holds the
        // whole category tree, so a fixed window never reaches the menu's own column.
        $start = strpos($markup, 'cat-header');
        $end = strpos($markup, 'brator-slide-menu-area');

        $this->assertNotFalse($start, 'Could not find the main menu area in the header.');
        $this->assertNotFalse($end, 'Could not find the end of the main menu area.');

        $row = substr($markup, $start, $end - $start);

        preg_match_all('/col-xxl-(\d+)/', $row, $units);

        $total = array_sum(array_map('intval', $units[1]));

        $this->assertSame(12, $total,
            "The main menu row's xxl columns add to {$total} of 12. Anything less and the menu is "
            .'narrower than its own labels, so they wrap onto two lines.');
    }

    public function test_the_header_has_no_link_that_goes_nowhere(): void
    {
        $markup = (string) file_get_contents(base_path('resources/views/partials/header.blade.php'));

        /*
         | Placeholder anchors are how the theme shipped a link it had no page for, and
         | test_every_internal_link_on_every_page_resolves cannot see them: it only collects
         | hrefs beginning with "/". That gap is exactly how the dead "Recently Viewed" link
         | survived every earlier sweep — while holding four of the menu row's twelve columns
         | and squeezing the navigation onto two lines.
        */
        foreach (['href="#_"', 'href="#-"'] as $dead) {
            $this->assertStringNotContainsString($dead, $markup,
                "The header contains a link that goes nowhere ({$dead}).");
        }
    }

    public function test_search_finds_a_part_by_its_number_in_any_formatting(): void
    {
        $reference = DB::table('product_cross_references')->first();

        $exact = $this->get('/search?s='.urlencode($reference->number))->assertOk()->getContent();
        $messy = $this->get('/search?s='.urlencode(strtolower(str_replace(' ', '-', $reference->number))))
            ->assertOk()->getContent();

        // Both spellings must find the part — that is what number_normalized is for.
        $this->assertStringContainsString('brator-product-single-item-area design-two', $exact);
        $this->assertStringContainsString('brator-product-single-item-area design-two', $messy);
    }

    /** @return list<string> */
    private function internalLinks(string $html): array
    {
        preg_match_all('/href="(\/[^"#]*)"/', $html, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $href) => ! $this->isStaticFile($href),
        )));
    }

    /**
     * Is this href a real file on disk rather than a route?
     *
     * This used to be `! str_starts_with($href, '/assets/')`, which assumed every static
     * file lives in the purchased theme's own directory. That held only while the theme was
     * the sole source of images. It stopped being true the moment the shop shipped pictures
     * of its own under /app/images: the hero preload then read as a broken link, because the
     * test client hands every path to the router and the router has no route for a JPEG.
     * The link was fine — the rule was wrong.
     *
     * Asking the filesystem is self-maintaining, and it is STRICTER than the old rule rather
     * than looser: a link to a static file that does not exist is no longer waved through
     * just because of the folder it points at.
     */
    private function isStaticFile(string $href): bool
    {
        $path = parse_url($href, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return is_file(public_path(ltrim(rawurldecode($path), '/')));
    }
}
