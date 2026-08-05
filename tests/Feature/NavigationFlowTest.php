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

                $status = $this->get($link)->getStatusCode();

                if ($status !== 200) {
                    $broken[] = "{$link} (status {$status}, linked from {$page})";
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
            fn (string $href) => ! str_starts_with($href, '/assets/')
        )));
    }
}
