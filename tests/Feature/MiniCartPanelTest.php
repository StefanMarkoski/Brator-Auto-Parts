<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
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
 * The header's mini-cart panel.
 *
 * The panel used to be reachable only by clicking the cart icon, and that same click navigated to
 * /cart — so it appeared for a frame or two and was replaced. It opens and closes on a click now,
 * stays open until it is dismissed, and can be acted on.
 *
 * IT WAS A HOVER PANEL FOR A DAY AND STEFAN ASKED FOR THAT REMOVED. Correctly: the cart icon sits
 * next to the search box and the vehicle picker, so it unfurled over the page on the way past.
 * Click is also the only behaviour a phone can have, so there is one behaviour now instead of two.
 *
 * WHAT THESE TESTS PIN is the markup the behaviour rests on, and one structural fact that is easy
 * to break by tidying: the panel must be a SIBLING of the icon link, not inside it. Inside, every
 * click in the panel would navigate to /cart.
 *
 * WHAT THEY CANNOT PROVE: the clicking itself, Escape, the outside-click close, and the in-place
 * refresh — all of that is public/app/storefront.js, which no PHP test loads. Verified in a real
 * headless Chrome instead:
 *
 *  - Clicking the icon: panel opacity 0 → 1, visibility visible, still on the same URL.
 *  - Clicking it again: closed. Clicking elsewhere, and Escape: closed.
 *  - Pointer onto the icon and away again without clicking: nothing happens, which is the point.
 *  - The Checkout link inside the open panel was the topmost element at its own coordinates, so
 *    it is genuinely clickable rather than covered.
 *  - Removing a line from the panel on the HOMEPAGE: badge 3 → 2, one row gone, still on "/",
 *    no page load. The theme's own × still closed the panel afterwards, which is the case a
 *    directly-bound handler would have failed.
 *  - Eight lines in the basket: the rows scroll inside their own region and the total and both
 *    buttons stay on screen.
 */
final class MiniCartPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogStructureSeeder::class);
    }

    public function test_the_icon_is_still_a_real_link_to_the_cart(): void
    {
        $html = $this->get('/cart')->assertOk()->getContent();

        /*
         | THE NO-JAVASCRIPT PATH, and Stefan's condition for letting the click open the panel:
         | storefront.js swallows the click only inside a handler that only exists once it has
         | run, so the bare anchor has to keep working on its own.
        */
        $this->assertMatchesRegularExpression(
            '/<a href="\/cart"[^>]*data-mini-cart-toggle/',
            $html,
            'The cart icon is no longer a plain <a href="/cart">. With JavaScript off there is '
            .'then no way to reach the cart from the header at all.'
        );
    }

    public function test_the_panel_is_a_sibling_of_the_link_and_not_inside_it(): void
    {
        $html = $this->get('/cart')->assertOk()->getContent();

        /*
         | The anchor must CLOSE before the panel opens. This is the fact the whole design rests
         | on: the panel sits inside .brator-cart-link ALONGSIDE the link rather than within it, so
         | a click on a row's remove button is not also a click on a link to /cart. Move the panel
         | inside the anchor and every interaction in it navigates away.
        */
        $this->assertMatchesRegularExpression(
            '/data-mini-cart-toggle.*?<\/a>\s*<div class="brator-cart-item-list" data-mini-cart>/s',
            $html,
            'The mini-cart panel is no longer a sibling of the cart link. Inside the anchor, '
            .'every click in the panel would navigate to /cart.'
        );
    }

    public function test_the_panel_carries_the_hooks_the_refresh_needs(): void
    {
        // Both headers, because the homepage and the shop pages use different partials and the
        // panel has to refresh identically on either.
        foreach (['/', '/cart'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            foreach (['data-mini-cart', 'data-mini-cart-badge', 'data-mini-cart-toggle'] as $hook) {
                $this->assertStringContainsString($hook, $html,
                    "{$path} no longer carries [{$hook}], so the panel cannot be refreshed "
                    .'without a page load.');
            }
        }
    }

    public function test_view_cart_and_checkout_no_longer_go_to_the_same_place(): void
    {
        $this->seedHomepage();
        $this->fillBasket();

        $html = $this->get('/')->assertOk()->getContent();

        // They were two buttons doing one thing. Checkout now lands on the checkout form itself.
        $this->assertStringContainsString('<a href="/cart">View Cart</a><a href="/cart#checkout">Checkout</a>', $html);
    }

    public function test_the_checkout_anchor_the_panel_points_at_really_exists(): void
    {
        $this->fillBasket();

        // An id, not a class — so no styling is introduced and ThemeFidelityTest is unaffected.
        $this->assertStringContainsString('id="checkout"', $this->get('/cart')->getContent(),
            'The mini-cart\'s Checkout button points at #checkout, which is nowhere on the page.');
    }

    public function test_the_panel_reflects_the_basket_server_side(): void
    {
        $this->seedHomepage();

        $empty = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Your cart is empty', $empty);

        $this->fillBasket();
        $filled = $this->get('/')->assertOk()->getContent();

        /*
         | The panel is fed by a view composer, so a full page load has always been correct — the
         | in-place refresh copies from exactly this markup. If the server render is wrong, the
         | refresh faithfully copies something wrong.
        */
        $this->assertStringNotContainsString('Your cart is empty', $filled);
        $this->assertStringContainsString('(2 items)', $filled);
    }

    public function test_the_lines_scroll_and_the_totals_do_not(): void
    {
        $this->seedHomepage();
        $this->fillBasket();

        /*
         | THE BUG. The theme shipped this panel with two hardcoded rows and no bound on its
         | height, because a demo basket never holds eleven things. Ours can: the rows are ~98px
         | each, so eight of them grew the panel past 1,200px and pushed the total and both buttons
         | off the bottom of the screen — on a panel with no scrollbar of its own. The one thing a
         | cart panel is for became unreachable exactly when the basket was worth the most.
         |
         | Asserted on BOTH headers, because the homepage and the shop pages use different
         | partials and a basket does not care which page you are standing on.
        */
        foreach (['/', '/cart'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(
                '<div data-mini-cart-scroll style="max-height: 296px; overflow-y: auto; overflow-x: hidden">',
                $html,
                "The mini-cart lines on {$path} have no scroll region, so a full basket pushes "
                .'the total and the Checkout button off the bottom of the screen.'
            );

            /*
             | ONLY THE ROWS. If the wrapper swallowed the totals block too, the panel would
             | scroll as one piece and the totals would be exactly as far out of reach — the bug,
             | with a scrollbar on it.
            */
            $this->assertMatchesRegularExpression(
                '/data-mini-cart-scroll.*?<\/div>\s*<div class="brator-cart-item-list-money-area">/s',
                $html,
                "The scroll region on {$path} now encloses the totals as well as the lines, so "
                .'scrolling to the bottom of the basket still hides the total.'
            );
        }
    }

    public function test_removing_a_line_from_the_panel_is_a_real_form(): void
    {
        $this->seedHomepage();
        $this->fillBasket();

        $html = $this->get('/')->assertOk()->getContent();

        /*
         | Intercepted by the script, but a genuine DELETE underneath — so the panel's × works
         | with JavaScript off too, and the interception is only about not throwing the shopper
         | onto /cart from whatever page they were browsing.
        */
        $this->assertMatchesRegularExpression(
            '/<form method="post" action="\/cart\/[A-Za-z0-9]+" data-basket-form>/',
            $html,
            'The mini-cart\'s remove button is no longer a real form marked for interception.'
        );
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
    }

    private function fillBasket(): void
    {
        $product = Product::factory()->create([
            'price_minor' => 100_000,
            'sale_price_minor' => null,
            'stock_quantity' => 50,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
    }

    /** The homepage needs its sections, or there is no header to inspect. */
    private function seedHomepage(): void
    {
        $this->seed(ProductSeederSmall::class);
        $this->seed(FitmentSeederSmall::class);
        $this->seed(MerchandisingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(ContentSeeder::class);
    }
}
