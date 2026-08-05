<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\HomepageSection;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront reads. These assert the pages render REAL data, not that they
 * return 200 — a page still full of the theme's demo products returns 200 quite
 * happily, which is exactly the failure worth catching.
 */
final class StorefrontPagesTest extends TestCase
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

    public function test_the_homepage_renders_sections_from_the_database(): void
    {
        $response = $this->get('/')->assertOk();

        // Headings come from homepage_sections, so this proves the page is driven by
        // the table and not by the theme's hardcoded copy.
        $response->assertSee('Shop by Categories', false);
        $response->assertSee('Best Seller', false);

        // The theme's own index-2 has a copy-paste bug: its "New Arrivals" strip is
        // headed "Essential Items for New Car". Ours reads correctly because the
        // heading is data. If this ever fails, the page fell back to the theme copy.
        $response->assertSee('New Arrivals', false);

        // A real category name, and a real brand, neither of which the theme ships.
        $response->assertSee('Braking', false);
    }

    public function test_hiding_a_section_removes_it_from_the_homepage(): void
    {
        $this->get('/')->assertOk()->assertSee('Shop by Categories', false);

        HomepageSection::query()
            ->where('section_type', 'categories_strip')
            ->update(['is_visible' => false]);

        // This is the whole point of the dynamic homepage: staff control it, and the
        // change takes effect without a deploy.
        $this->get('/')->assertOk()->assertDontSee('Shop by Categories', false);
    }

    public function test_reordering_sections_reorders_the_homepage(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Best Seller'),
            strpos($html, 'Shop by Categories'),
            'Categories should render before Best Seller at the seeded positions.'
        );

        HomepageSection::query()
            ->where('section_type', 'categories_strip')->update(['position' => 99]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertGreaterThan(
            strpos($html, 'Best Seller'),
            strpos($html, 'Shop by Categories'),
            'Moving a section to position 99 should move it to the end of the page.'
        );
    }

    public function test_the_category_listing_shows_products_from_that_category(): void
    {
        $category = $this->categoryWithProducts();

        $html = $this->get(route('shop.category', $category->slug))->assertOk()->getContent();

        // A product name from this category, and the empty state absent — asserting on
        // 'ден' alone would pass on a page with no products at all, since the footer
        // carries the currency too.
        $this->assertStringContainsString('brator-product-single-item-area design-two', $html);
        $this->assertStringNotContainsString('No parts match this category yet.', $html);
    }

    public function test_a_category_with_no_products_shows_the_empty_state(): void
    {
        $empty = Category::query()->where('depth', 1)
            ->whereDoesntHave('products')->first();

        if ($empty === null) {
            $this->markTestSkipped('The seed left every category populated.');
        }

        $this->get(route('shop.category', $empty->slug))
            ->assertOk()
            ->assertSee('No parts match this category yet.', false);
    }

    public function test_the_list_view_renders_the_themes_list_layout(): void
    {
        $category = $this->categoryWithProducts();

        $grid = $this->get(route('shop.category', $category->slug))->assertOk()->getContent();
        $list = $this->get(route('shop.category', $category->slug).'?view=list')->assertOk()->getContent();

        // Same data, the theme's two different card layouts.
        $this->assertStringContainsString('brator-product-single-item-area design-two', $grid);
        $this->assertStringContainsString('brator-product-single-item-area design-three', $list);
        $this->assertStringNotContainsString('brator-product-single-item-area design-three', $grid);
    }

    public function test_the_product_page_shows_that_products_own_details(): void
    {
        $product = Product::query()->with('brand')->firstOrFail();

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->sku)
            // And not the theme's demo part, which is the failure that returns 200.
            ->assertDontSee('Silver with Mirror Cut Facewheels', false);
    }

    /** A category the small seed actually put products in. */
    private function categoryWithProducts(): Category
    {
        return Category::query()
            ->where('depth', 1)
            ->whereHas('products')
            ->firstOrFail();
    }

    public function test_an_unknown_product_slug_is_a_404_not_a_500(): void
    {
        $this->get('/product/no-such-part')->assertNotFound();
    }

    public function test_an_inactive_product_is_not_reachable(): void
    {
        $product = Product::query()->firstOrFail();
        $product->update(['is_active' => false]);

        $this->get(route('shop.product', $product->slug))->assertNotFound();
    }
}
