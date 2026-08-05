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

        $this->get('/')->assertOk()->assertSee($product->name, false);
    }

    public function test_the_product_page_specification_table_shows_that_products_attributes(): void
    {
        $product = Product::query()->with('brand')->firstOrFail();

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee($product->sku, false)
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
