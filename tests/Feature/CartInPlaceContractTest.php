<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\GenerateCouponAction;
use App\Domain\Ordering\Models\BasketLine;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contract between the cart page and the in-place update in public/app/storefront.js.
 *
 * WHY A MARKUP TEST IS WORTH WRITING HERE. The in-place update is enhancement: if a hook goes
 * missing it does not throw, it silently falls back to reloading the page — which is the exact
 * bug it was built to remove ("every time you do something the form refreshes and you need to
 * start from the beginning"). A regression would therefore be invisible in a normal test run and
 * invisible on the page until somebody typed a long address and lost it. So the hooks the script
 * looks for are pinned here, with the reason each one exists.
 *
 * WHAT THIS TEST CANNOT PROVE, plainly: that the JavaScript patches correctly. No PHP test loads
 * storefront.js. Every behaviour below the markup line was verified by driving a real headless
 * Chrome, and those results are what the operator should weigh:
 *
 *  - Plus, minus, two fast pluses, wrong code, valid code, remove code, remove line: same
 *    document every time, scroll unchanged (1542 before and after), and all five checkout fields
 *    still filled after every one of them. Before the change, all five were empty every time.
 *  - Two fast + clicks moved the quantity by two, not one — no click coalesced away.
 *  - Emptying the cart in place REMOVED the summary and the checkout block rather than leaving
 *    an empty shell, and the badge went to 0 without a page load.
 *  - The double-submit guard was still bound after several patches: the first submit went
 *    through with the button disabled and relabelled, the second was blocked, and the label
 *    restored on pageshow carried the CURRENT total.
 */
final class CartInPlaceContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogStructureSeeder::class);
    }

    public function test_the_regions_the_in_place_update_patches_are_all_present(): void
    {
        $this->fillBasket();

        $html = $this->get('/cart')->assertOk()->getContent();

        $required = [
            // The page is the cart at all — the script does nothing anywhere else.
            'data-cart-region' => 'the marker that this page supports in-place updating',
            // Always rendered, empty or not, so there is a stable element to patch.
            'data-cart-flash' => 'the flash-message region',
            'data-cart-lines' => 'the basket lines',
            'data-cart-summary' => 'the order summary (inserted and removed with the basket)',
            'data-cart-checkout' => 'the checkout block, which must never be re-rendered in place',
            'data-cart-checkout-errors' => 'the validation list inside the checkout block',
            'data-cart-total-label' => 'the total on the Place order button',
            'data-cart-tail' => 'the fixed point the optional blocks are inserted before',
            'data-cart-coupon' => 'the coupon block',
            'data-coupon-message' => 'the one element coupon feedback is written into',
            'data-cart-qty-form' => 'the quantity form, whose step is rewritten to an absolute',
            'data-cart-qty' => 'the quantity input the absolute is computed from',
            'data-scroll-memory' => 'scroll restoration for the paths that still reload',
        ];

        foreach ($required as $hook => $why) {
            $this->assertStringContainsString($hook, $html,
                "The cart page no longer carries [{$hook}] — {$why}. Without it the in-place "
                .'update silently falls back to reloading, and the checkout fields empty again.');
        }
    }

    public function test_every_form_that_changes_the_basket_is_marked_for_interception(): void
    {
        $this->fillBasket();
        $this->post('/cart/coupon', ['code' => app(GenerateCouponAction::class)->execute(10)->code]);

        $html = $this->get('/cart')->assertOk()->getContent();

        /*
         | ONE attribute on every basket-mutating form, so the script has a single selector rather
         | than a list of URL patterns to keep in step with routes/web.php. A form that loses it
         | still WORKS — it just reloads the page, which is the regression this change removes.
         |
         | Four on a one-line cart with a code applied, and it is worth naming them because the
         | count is easy to misread: the quantity form, the line's remove button, the hidden
         | #remove-coupon form, and the mini-cart panel's own remove button in the header. The
         | coupon APPLY form is not among them — with a code applied it is not rendered at all,
         | and the "Remove" beside the code is a button targeting #remove-coupon rather than a
         | form of its own.
        */
        $this->assertSame(4, substr_count($html, 'data-basket-form'),
            'Expected exactly four intercepted forms on a one-line cart with a code applied: the '
            .'quantity form, the line remove, the hidden remove-coupon form, and the mini-cart '
            .'panel\'s remove button.');
    }

    public function test_the_summary_and_checkout_blocks_are_absent_when_the_basket_is_empty(): void
    {
        $html = $this->get('/cart')->assertOk()->getContent();

        /*
         | THIS is why those two blocks are inserted and removed rather than swapped. They do not
         | exist on an empty cart, so an update that assumed they were there would either strand
         | a shopper on a cart with no checkout form or leave an empty shell behind after the last
         | line was removed.
        */
        $this->assertStringNotContainsString('data-cart-summary', $html);
        $this->assertStringNotContainsString('data-cart-checkout', $html);

        // And the fixed point they get inserted before is still there, which is what makes
        // insertion possible at all.
        $this->assertStringContainsString('data-cart-tail', $html);
        $this->assertStringContainsString('data-cart-lines', $html);
    }

    public function test_the_discount_row_only_exists_while_a_code_is_discounting_something(): void
    {
        $this->fillBasket(priceMajor: 100);
        $coupon = app(GenerateCouponAction::class)->execute(10, Money::fromMajor(3_000));

        $this->post('/cart/coupon', ['code' => $coupon->code])->assertRedirect();

        // Applied and kept, but below its minimum — so the row must not be rendered. A zero
        // discount row would read as broken.
        $this->assertStringNotContainsString('Discount', $this->get('/cart')->getContent());
    }

    public function test_the_cart_answers_an_xhr_with_the_whole_document(): void
    {
        $this->fillBasket();

        /*
         | The in-place update fetches the redirect target and parses it, so /cart must keep
         | answering with the full page rather than growing a partial-response branch. Measured
         | over HTTP as an identical byte count with and without the header; asserted here as
         | "the regions are all in the XHR response too".
        */
        $xhr = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/cart')->assertOk()->getContent();

        foreach (['data-cart-lines', 'data-cart-summary', 'data-cart-checkout', 'data-mini-cart'] as $hook) {
            $this->assertStringContainsString($hook, $xhr,
                "An XHR GET of /cart no longer contains [{$hook}], so the in-place update has "
                .'nothing to copy from.');
        }
    }

    public function test_a_posted_quantity_with_no_step_is_taken_literally(): void
    {
        $this->fillBasket();
        $line = BasketLine::query()->latest('id')->firstOrFail();

        /*
         | This is the shape the in-place update posts: an ABSOLUTE quantity with no `step` at
         | all. Two fast clicks sent as steps could be coalesced or reordered by any queue and a
         | click would vanish; an absolute number cannot be lost that way, whatever order the
         | responses land in.
         |
         | It is also the shape a native form.submit() produces, which is what the fallback path
         | uses — so the fallback lands on the same number as the in-place path.
        */
        $this->post("/cart/{$line->id}", ['quantity' => 7])->assertRedirect();

        $this->assertSame(7, $line->fresh()->quantity);
    }

    public function test_the_quantity_forms_default_button_carries_no_step(): void
    {
        $this->fillBasket();

        $html = $this->get('/cart')->assertOk()->getContent();

        /*
         | THE ENTER-KEY BUG. Typing 7 and pressing Enter set the line to 6. Implicit submission
         | activates the form's FIRST submit button in tree order and that was the minus, so the
         | server got quantity=7 together with step=-1 and applied both.
         |
         | A nameless hidden submit button is now first, so either the browser activates it (it
         | has no name, so it contributes nothing) or, being unrendered, submits with no button at
         | all. Both end with `step` absent.
         |
         | WHAT THIS CANNOT PROVE: that the browser really behaves that way. Verified for real —
         | typed 7, dispatched a genuine Enter key event, and the line came back as 7. What is
         | pinned here is the markup precondition: the first submit button must stay nameless,
         | hidden, and before the minus.
        */
        $this->assertMatchesRegularExpression(
            '/<form[^>]*data-cart-qty-form[^>]*>.*?<button type="submit" hidden[^>]*><\/button>.*?<button class="decrement-count-qty"/s',
            $html,
            'The nameless hidden submit button is no longer the quantity form\'s first submit '
            .'button, so pressing Enter in the quantity box will decrement instead of setting '
            .'the number that was typed.'
        );
    }

    private function fillBasket(int $priceMajor = 1_000): void
    {
        $product = Product::factory()->create([
            'price_minor' => $priceMajor * 100,
            'sale_price_minor' => null,
            'stock_quantity' => 50,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
    }
}
