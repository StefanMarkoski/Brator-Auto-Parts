<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\GenerateCouponAction;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The live coupon check — GET /cart/coupon/check.
 *
 * WHAT THESE TESTS ARE REALLY FOR is the first one: an inactive code and a code that never
 * existed must come back BYTE-IDENTICAL. That property is the whole reason this endpoint is
 * allowed to exist, and it is the kind of thing a well-meaning "let's give the shopper a better
 * message" change breaks without anybody noticing. Pinned here so it cannot be lost quietly.
 *
 * WHAT THEY CANNOT PROVE, said plainly:
 *
 *  - That the field debounces. The 300ms timer and the two-character floor live in
 *    public/app/storefront.js, which no PHP test loads. Verified in a real browser instead:
 *    twenty-two keystrokes produced three requests, and one character produced none.
 *  - That the tick and the cross render. Also browser-verified — typing "GH" showed
 *    "✗ That code is not valid." under the input, and a real code showed "✓ 10% off any order".
 *  - That the endpoint is safe in the FUTURE. It is only safe while every usable code is
 *    advertised on the homepage, which is a product fact no test can hold still. The condition
 *    is recorded in BasketController::checkCoupon, and the last test below at least fails loudly
 *    if the advertising stops.
 */
final class CouponLiveCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogStructureSeeder::class);

        /*
         | These tests hit the same route many times and the route is throttled on purpose, so the
         | throttle is off for all of them EXCEPT the one that deliberately measures it.
        */
        $this->withoutMiddleware(ThrottleRequests::class);

        Cache::flush();
    }

    /**
     * The limiter lives in the cache, and one test in here deliberately exhausts it.
     *
     * Left exhausted it outlives this class for the rest of the PHP process, and then the next
     * test anywhere that posts a coupon fails with a 429 that has nothing to do with what it was
     * checking. Cross-test state is the worst kind of flake to chase, because the failure moves
     * when the suite is re-ordered — so it is cleaned up here rather than hoped about.
     */
    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_a_retired_code_is_indistinguishable_from_a_code_that_never_existed(): void
    {
        $retired = app(GenerateCouponAction::class)->execute(10);
        $retired->update(['is_active' => false]);

        $neverExisted = $this->check('SAVE10ZZZZ')->assertOk()->json();
        $switchedOff = $this->check($retired->code)->assertOk()->json();

        /*
         | Identical, and asserted as whole payloads rather than field by field — a difference
         | anywhere in the body is a difference an attacker can read, including one somebody adds
         | later in good faith. This is what stops the endpoint being used to enumerate codes the
         | shop has retired.
        */
        $this->assertSame($neverExisted, $switchedOff,
            'A switched-off code answers differently from an unknown one, which turns this '
            .'endpoint into a way to discover retired codes.');

        $this->assertSame(
            ['known' => false, 'ok' => false, 'message' => 'That code is not valid.'],
            $neverExisted
        );
    }

    public function test_the_live_check_says_the_same_thing_the_apply_button_says(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $live = $this->check('NOPENOPE01')->json('message');

        // The field and the button disagreeing about the same code is worse than either of them
        // being unhelpful, so the wording comes from one place.
        $applied = $this->post('/cart/coupon', ['code' => 'NOPENOPE01'])->assertRedirect();

        $this->assertSame('That code is not valid.', $live);
        $this->assertSame('That code is not valid.', session('coupon_error'));
        $this->assertNotNull($coupon->code);
        $this->assertNotNull($applied);
    }

    public function test_a_usable_code_on_a_qualifying_basket_is_confirmed(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $this->addToCart($this->product(1_000, 10), 1);

        $this->check($coupon->code)->assertOk()->assertExactJson([
            'known' => true,
            'ok' => true,
            'message' => '10% off any order',
        ]);
    }

    public function test_a_real_code_below_its_minimum_is_known_but_not_yet_usable(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10, Money::fromMajor(3_000));
        $this->addToCart($this->product(1_000, 10), 1);

        $body = $this->check($coupon->code)->assertOk()->json();

        /*
         | known WITHOUT ok, and the distinction matters to the shopper rather than to the code:
         | applying this really would be accepted and kept, so the field must not mark it wrong.
         | Telling somebody a working code is invalid costs them the discount, which is the
         | expensive direction to be wrong in.
        */
        $this->assertTrue($body['known']);
        $this->assertFalse($body['ok']);
        $this->assertStringContainsString('short', $body['message']);
        $this->assertNotSame('That code is not valid.', $body['message']);
    }

    public function test_the_check_is_case_and_whitespace_insensitive_like_the_real_apply(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $this->addToCart($this->product(1_000, 10), 1);

        $this->check('  '.strtolower($coupon->code).'  ')->assertOk()->assertJson(['known' => true]);
    }

    public function test_the_check_applies_nothing(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $this->addToCart($this->product(1_000, 10), 1);

        $this->check($coupon->code)->assertOk();

        // It is called on a keystroke, so it must be a read and only a read. A check that quietly
        // applied the code would mean a half-typed guess changing what somebody owes.
        $this->assertStringNotContainsString('10% off', $this->get('/cart')->getContent(),
            'The live check applied the coupon instead of only reporting on it.');
        $this->assertSame(0, $coupon->fresh()->times_used);
    }

    public function test_a_missing_or_overlong_code_is_refused_rather_than_looked_up(): void
    {
        $this->getJson('/cart/coupon/check')->assertStatus(422);
        $this->check(str_repeat('A', 11))->assertStatus(422);
    }

    public function test_both_coupon_endpoints_are_rate_limited(): void
    {
        // The one thing this test must NOT do is run without the throttle middleware setUp
        // removes for everything else.
        $this->withMiddleware(ThrottleRequests::class);

        /*
         | Before this change POST /cart/coupon had no limit at all — measured in the browser as
         | forty wrong codes accepted back to back — which made walking an 810 000-candidate code
         | space a matter of patience. The live check would have made it cheaper still.
         |
         | The numbers are asserted as "refuses eventually and well under a hundred" rather than
         | pinned to 20 and 30 exactly: the useful property is that a ceiling exists, and pinning
         | the figure would make tuning it a test change.
        */
        $refusedCheckAfter = null;

        for ($i = 1; $i <= 100; $i++) {
            if ($this->check('SAVE10ZZZ'.($i % 10))->status() === 429) {
                $refusedCheckAfter = $i;
                break;
            }
        }

        $this->assertNotNull($refusedCheckAfter, 'GET /cart/coupon/check never refuses.');
        $this->assertLessThan(100, $refusedCheckAfter);

        $refusedApplyAfter = null;

        for ($i = 1; $i <= 100; $i++) {
            if ($this->post('/cart/coupon', ['code' => 'SAVE10YYY'.($i % 10)])->status() === 429) {
                $refusedApplyAfter = $i;
                break;
            }
        }

        $this->assertNotNull($refusedApplyAfter, 'POST /cart/coupon never refuses — the '
            .'guessing oracle this change was meant to close is still open.');
        $this->assertLessThan(100, $refusedApplyAfter);
    }

    public function test_a_rejected_code_is_still_in_the_field_afterwards(): void
    {
        $this->addToCart($this->product(1_000, 10), 1);

        $this->post('/cart/coupon', ['code' => 'SAVE10ZZZZ'])->assertRedirect();

        // withInput(), so old('code') repopulates. Without it a single mistyped character meant
        // typing all ten again — on a page that also reloaded and emptied the checkout fields.
        $this->assertSame('SAVE10ZZZZ', session('_old_input.code'));
    }

    public function test_every_usable_code_is_still_advertised_on_the_homepage(): void
    {
        /*
         | THE CONDITION THE LIVE CHECK RESTS ON, guarded rather than assumed.
         |
         | The endpoint can only ever confirm a code that is active, and every active code is
         | printed in the homepage top bar — so it reveals nothing a visitor cannot read off the
         | front page. If that ever stops being true, the check becomes a real discovery oracle
         | and has to be dropped. This test is what makes that change loud instead of silent.
         |
         | It does NOT prove the endpoint is safe. It proves the one premise the safety argument
         | is built on, which is as much as a test can do here.
        */
        $advertised = app(GenerateCouponAction::class)->execute(10);
        $retired = app(GenerateCouponAction::class)->execute(20);
        $retired->update(['is_active' => false]);

        $homepage = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString($advertised->code, $homepage,
            'A usable code is NOT advertised. The live coupon check is only safe while every '
            .'code it can confirm is already public — see BasketController::checkCoupon.');
        $this->assertStringNotContainsString($retired->code, $homepage,
            'A retired code is still being advertised.');
    }

    private function check(string $code): TestResponse
    {
        return $this->getJson('/cart/coupon/check?code='.urlencode($code));
    }

    private function product(int $priceMajor, int $stock): Product
    {
        return Product::factory()->create([
            'price_minor' => $priceMajor * 100,
            'sale_price_minor' => null,
            'stock_quantity' => $stock,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    private function addToCart(Product $product, int $quantity): void
    {
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertRedirect();
    }
}
