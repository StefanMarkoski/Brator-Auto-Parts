<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adding to the basket must not throw the shopper onto /cart.
 *
 * Stefan's objection, and he was right: "is it proper to navigate to cart? I think not, more like
 * just a pop ... {product} ... ден added to cart". Being redirected after every single add is a
 * shop arguing with the person browsing it — you lose your place on the product page, and on a
 * listing you lose the results you were reading.
 *
 * storefront.js now posts these forms in the background and announces the result in the header's
 * mini-cart, which opens itself for five seconds. That also gives the panel the job it never had,
 * which was the whole of Stefan's separate complaint about it.
 *
 * WHAT THIS TEST PINS: the markup hooks on all four add surfaces, the announcement slot, and the
 * sentence itself — including the price, since that sentence is now the entire confirmation rather
 * than a footnote on a page the shopper is about to be dragged to anyway.
 *
 * WHAT IT CANNOT PROVE, plainly: that the page does not navigate, that the panel opens, that it
 * closes after five seconds, or that hovering holds it open. All of that is public/app/
 * storefront.js, which no PHP test loads. Driven in a real headless Chrome instead:
 *
 *  - Product page, quantity 3, real mouse click on Add To Cart: stayed on
 *    /product/skf-control-arm-bush-reinforced-4467, same document, badge 0 → 3, panel open,
 *    announcement "SKF Control Arm Bush reinforced — 1.047,16 ден added to your cart."
 *  - Left alone it closed itself; with the pointer on the icon it was still open after six
 *    seconds, and closed once the pointer left.
 *  - Listing card, real mouse click: stayed on /shop/braking, same document, all twelve results
 *    still on screen, badge 7 → 8, announcement "Japanparts Brake Fluid DOT 4 1L OE quality —
 *    429,31 ден added to your cart."
 */
final class AddToCartStaysOnThePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogStructureSeeder::class);
    }

    public function test_the_confirmation_sentence_names_the_product_and_its_price(): void
    {
        $product = $this->product(priceMajor: 1_000);

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect(route('cart'));

        /*
         | The price is IN the sentence because the sentence is now the whole confirmation,
         | delivered on the page the shopper is still standing on. "What did that cost me" is the
         | obvious next question and the panel answering it three lines lower is not the same as
         | the sentence answering it.
         |
         | Formatted through Money, so it cannot disagree with the figure printed above the button.
        */
        $this->assertSame(
            $product->name.' — 1.000,00 ден added to your cart.',
            session('status')
        );
    }

    public function test_the_sentence_quotes_the_sale_price_when_there_is_one(): void
    {
        $product = $this->product(priceMajor: 1_000);
        $product->update(['sale_price_minor' => 60_000]);

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        // effectivePrice, not price_minor. Announcing the pre-sale figure would be the shop
        // quoting a number it is not charging.
        $this->assertStringContainsString('600,00 ден', (string) session('status'));
        $this->assertStringNotContainsString('1.000,00', (string) session('status'));
    }

    public function test_the_redirect_to_the_cart_is_untouched(): void
    {
        $product = $this->product(priceMajor: 100);

        /*
         | THE NO-JAVASCRIPT PATH. The interception lives entirely in a handler that only exists
         | once storefront.js has run, so with scripts off this is still a plain form that posts
         | and lands on the cart with the flash above. Verified in the browser too, with
         | storefront.js blocked at the network layer.
        */
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect(route('cart'));
    }

    public function test_every_add_to_cart_surface_is_marked_for_interception(): void
    {
        $product = $this->product(priceMajor: 100);

        /*
         | Asserted as an INVARIANT over whatever add forms the page happens to render, rather
         | than as a count. The first version of this test expected exactly two on the product
         | page and failed: "Add All To Cart" only exists when the product HAS companion parts,
         | and a bare factory product has none. A count would also have said nothing about a
         | fifth add form somebody adds next year.
         |
         | So: every form posting to /cart/add or /cart/add-many, anywhere, must carry the hook.
         | One that does not still WORKS — it just redirects to /cart, which is the behaviour
         | Stefan asked us to remove.
        */
        /*
         | The search results rather than /shop, and both view modes. /shop is the DEPARTMENT
         | index — it lists categories, not products, so it renders no add button at all; the
         | first version of this test looked there and found nothing. Search is used instead of a
         | category page because a factory product is not filed under a category, and the two view
         | modes matter because grid and list are separate partials with their own copy of the form.
        */
        $pages = [
            route('shop.product', $product->slug),
            '/search?s='.urlencode($product->name),
            '/search?s='.urlencode($product->name).'&view=list',
        ];

        $found = 0;

        foreach ($pages as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            preg_match_all('/<form[^>]*action="\/cart\/add(?:-many)?"[^>]*>/', $html, $matches);

            $this->assertNotEmpty($matches[0], "No add-to-cart form rendered on {$page} at all.");

            foreach ($matches[0] as $tag) {
                $found++;

                $this->assertStringContainsString('data-basket-add', $tag,
                    "An add-to-cart form on {$page} is not marked for interception, so using it "
                    ."navigates away from the page:\n  ".$tag);
                $this->assertStringContainsString('data-basket-form', $tag,
                    "An add-to-cart form on {$page} carries data-basket-add without "
                    .'data-basket-form, so nothing intercepts it.');
            }
        }

        $this->assertGreaterThanOrEqual(3, $found, 'Expected add forms on all three surfaces.');
    }

    public function test_the_confirmation_is_a_viewport_toast_and_not_a_slot_in_the_header(): void
    {
        $product = $this->product(priceMajor: 100);

        /*
         | THE REGRESSION THIS GUARDS. The confirmation used to be a <p> inside the mini-cart
         | panel. It was correct in every way that a test could see — right sentence, right money,
         | opened on time, closed on time — and Stefan still reported it as not working, because
         | it rendered in the HEADER. Measured in a browser at y = -2500: two and a half thousand
         | pixels above the top of the window, while he was scrolled down a listing looking at the
         | button he had just pressed.
         |
         | It is now built by storefront.js as an element fixed to the bottom of the viewport, so
         | there is nothing in the server response to assert a position on. What CAN be asserted
         | is that the old slot has not crept back in — a second confirmation in the header would
         | be the bug returning, whatever the toast does.
        */
        foreach (['/', route('shop.product', $product->slug), '/cart'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'data-basket-announcement',
                $html,
                "{$path} renders the old in-header announcement slot again. The confirmation "
                .'belongs in the viewport-fixed toast (showBasketToast in storefront.js), because '
                .'a message in the header is off screen for anyone scrolled down a listing.'
            );
        }

        $script = file_get_contents(public_path('app/storefront.js'));

        $this->assertStringContainsString('showBasketToast', $script,
            'The toast that reports an add is gone from storefront.js, so adding a part now '
            .'confirms nothing at all.');
        $this->assertStringContainsString('position: fixed', $script,
            'The toast is no longer fixed to the viewport, which is the entire reason it exists: '
            .'anything positioned in the document flows back up into the header.');
    }

    private function product(int $priceMajor): Product
    {
        return Product::factory()->create([
            'price_minor' => $priceMajor * 100,
            'sale_price_minor' => null,
            'stock_quantity' => 50,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }
}
