<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A shop served over https must not redirect to http.
 *
 * Behind a TLS terminator Laravel sees a plain HTTP connection, so it built every redirect
 * as http:// on pages the browser had loaded over https. MEASURED on the deployed shop:
 *
 *   POST /cart/add  ->  location: http://brator-….laravel.cloud/cart
 *
 * Browsers block that as mixed content. The consequence was not a broken link, it was a
 * broken feature: storefront.js posts the add in the background, the blocked response made
 * that fetch fail AFTER the server had already added the item, the code fell back to a
 * native form submit, and the shopper ended up with two of the product and thrown onto the
 * cart page — precisely the behaviour the in-place cart work removed.
 */
final class HttpsUrlsAreGeneratedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    private function buyable(): Product
    {
        $product = Product::query()->where('stock_status', StockStatus::InStock)->firstOrFail();
        $product->update(['stock_quantity' => 50, 'published_at' => now()->subDay(), 'is_active' => true]);

        return $product->refresh();
    }

    public function test_adding_to_the_cart_redirects_over_https_when_the_site_is_https(): void
    {
        // What AppServiceProvider does when APP_URL is https. Applied here directly because
        // the provider has already booted by the time a test runs.
        URL::forceScheme('https');

        $response = $this->post('/cart/add', [
            'product_id' => $this->buyable()->id,
            'quantity' => 1,
        ]);

        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://', $location,
            "Redirect after add-to-cart was not https: {$location}");
    }

    public function test_local_http_is_left_alone(): void
    {
        // The rule is keyed off APP_URL, so a plain http deployment keeps working. Without
        // this, forcing the scheme unconditionally would break local development.
        $this->assertStringStartsWith('http://', config('app.url'));

        $response = $this->post('/cart/add', [
            'product_id' => $this->buyable()->id,
            'quantity' => 1,
        ]);

        $this->assertStringStartsWith('http://', (string) $response->headers->get('Location'));
    }
}
