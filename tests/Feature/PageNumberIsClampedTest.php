<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A page number past the end of a listing, which was a public amplification.
 *
 * ?page was clamped at the bottom and never at the top, so window() got a $current that
 * did not exist. range()'s start then exceeded its end, PHP silently counted DOWNWARDS,
 * and every one of those numbers rendered as an <a>. MEASURED against the running app
 * before the fix, unauthenticated GET, no session:
 *
 *   /shop/braking?page=1            210 728 bytes
 *   /shop/braking?page=1000         950 pagination links
 *   /shop/braking?page=100000    23 195 106 bytes   — 110x the normal page
 *   /shop/braking?page=2147483647       HTTP 500    "range exceeds maximum array size"
 *
 * After: 155 894 bytes and 200 for every one of them.
 *
 * WHY THIS TEST BUILDS ITS OWN CATALOGUE, which is the part worth reading. My first
 * version seeded ProductSeederSmall and asserted against /shop/braking. Every assertion
 * passed — and then passed again with the fix reverted. window() returns early when there
 * are 7 pages or fewer, and 40 seeded products spread over the tree never reach 8 pages in
 * one category, so the exploding branch was never entered. The test looked like a guard
 * and was decoration. It needs MORE THAN 84 products in one listing (12 per page × 7) to
 * mean anything, so it makes them.
 */
final class PageNumberIsClampedTest extends TestCase
{
    use RefreshDatabase;

    /** 100 products at 12 a page is 9 pages — comfortably past window()'s early return. */
    private const PRODUCTS = 100;

    private const PAGES_NEEDED = 9;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);

        $this->category = Category::query()->where('is_active', true)->firstOrFail();

        Product::factory()
            ->count(self::PRODUCTS)
            ->create()
            ->each(fn (Product $product) => $product->categories()->attach($this->category->id));
    }

    private function url(int $page): string
    {
        return "/shop/{$this->category->slug}?page={$page}";
    }

    private function pageLinkCount(string $html): int
    {
        return preg_match_all('/class="[^"]*\bpage-numbers\b[^"]*"/', $html);
    }

    /**
     * The theme's markup has room for about seven numbers, so this is the real ceiling
     * with slack. Before the fix a listing of N pages emitted (requested - N) links.
     */
    private const SANE_LINK_CEILING = 12;

    public function test_the_fixture_is_actually_big_enough_to_trip_the_bug(): void
    {
        // Guards the guard. If this ever fails, every assertion below is passing vacuously
        // because window() returns early at 7 pages or fewer — which is exactly how the
        // first version of this file managed to pass against the broken code.
        $response = $this->get($this->url(1));
        $response->assertOk();

        $this->assertGreaterThan(
            7,
            self::PAGES_NEEDED,
            'window() returns early at 7 pages or fewer.',
        );
        $this->assertStringContainsString(
            (string) self::PAGES_NEEDED,
            $response->getContent(),
            'The listing does not appear to have '.self::PAGES_NEEDED.' pages — the fixture shrank.',
        );
    }

    public function test_a_page_past_the_end_does_not_emit_one_link_per_skipped_page(): void
    {
        $absurd = $this->get($this->url(500));
        $absurd->assertOk();

        // With the bug: range(499, 8) counts downwards and renders 492 links.
        $this->assertLessThanOrEqual(
            self::SANE_LINK_CEILING,
            $this->pageLinkCount($absurd->getContent()),
            'A past-the-end page emitted one link per page between the real last page and the requested one.',
        );
    }

    public function test_a_page_far_past_the_end_does_not_explode_the_document(): void
    {
        $sane = $this->get($this->url(1));
        $sane->assertOk();

        $absurd = $this->get($this->url(100000));
        $absurd->assertOk();

        // Orders of magnitude, not bytes — hence the generous multiplier.
        $this->assertLessThan(
            strlen($sane->getContent()) * 2,
            strlen($absurd->getContent()),
        );
    }

    public function test_the_largest_possible_page_number_is_not_a_500(): void
    {
        // PHP_INT_MAX as a 32-bit int is what tipped range() into a hard error rather than
        // merely a huge array.
        $this->get($this->url(2147483647))->assertOk();
        $this->get('/search?s=a&page=50000')->assertOk();
    }

    public function test_the_results_line_never_counts_past_the_total(): void
    {
        $response = $this->get($this->url(100000));
        $response->assertOk();

        // It used to render "637 - 628 of 628 results" over an empty grid.
        preg_match('/(\d+)\s*-\s*(\d+)\s*<\/span>\s*of\s*([\d,]+)/', $response->getContent(), $m);

        $this->assertNotEmpty($m, 'The theme results line was not found — the assertions below would pass vacuously.');
        $this->assertLessThanOrEqual((int) $m[2], (int) $m[1], 'Shown-from ran past shown-to.');
        $this->assertLessThanOrEqual((int) str_replace(',', '', $m[3]), (int) $m[2], 'Shown-to ran past the total.');
    }

    public function test_a_nested_attribute_parameter_is_ignored_rather_than_a_500(): void
    {
        // ?attr[a][b][c]=1 is a legal query string. strval() on the nested array raised
        // "Array to string conversion", which the handler promotes to a 500.
        $this->get("/shop/{$this->category->slug}?attr[a][b][c]=1")->assertOk();
        $this->get("/shop/{$this->category->slug}?attr[brand][]=")->assertOk();
    }
}
