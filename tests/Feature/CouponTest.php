<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\GenerateCouponAction;
use App\Domain\Ordering\Actions\PlaceReceiptAction;
use App\Domain\Ordering\DTOs\CheckoutDetails;
use App\Domain\Ordering\Exceptions\ReceiptIsSealedException;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use App\Domain\Ordering\Models\Coupon;
use App\Domain\Ordering\Services\AppliedCoupon;
use App\Models\Enums\UserRole;
use App\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Discount codes.
 *
 * The arithmetic is what these tests are for. A discount touches the taxable base, the
 * delivery threshold and the receipt at once, and every one of those has a wrong answer that
 * looks plausible.
 */
final class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogStructureSeeder::class);
    }

    public function test_a_generated_code_is_ten_readable_characters(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->assertSame(10, strlen($coupon->code));
        $this->assertStringStartsWith('SAVE10', $coupon->code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $coupon->code);

        /*
         | The no-confusable-characters rule applies to the RANDOM part, not to the whole code.
         |
         | My first version asserted it over the lot and failed on "SAVE10XG5G" — because of
         | the 0 in "10". That 0 is not ambiguous: it is part of a number the customer hears as
         | "save ten", and refusing it would mean no coupon could ever be 10%, 100% or 20%.
         | The characters that get mistyped are the ones with no context, so those are the ones
         | the filler avoids.
        */
        $filler = substr($coupon->code, strlen('SAVE10'));

        $this->assertDoesNotMatchRegularExpression('/[O0I1LU]/', $filler,
            'The random part of the code must avoid characters people mishear or mistype.');
    }

    public function test_generated_codes_do_not_collide(): void
    {
        $codes = [];

        for ($i = 0; $i < 25; $i++) {
            $codes[] = app(GenerateCouponAction::class)->execute(10)->code;
        }

        // The alphabet is small BECAUSE the code has to be readable, which is exactly what
        // makes a collision plausible enough to handle rather than assume away.
        $this->assertCount(25, array_unique($codes));
    }

    public function test_a_nonsense_percentage_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);

        app(GenerateCouponAction::class)->execute(0);
    }

    public function test_a_coupon_with_no_minimum_discounts_any_basket(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->assertTrue($coupon->appliesTo(Money::fromMajor(1)));
        $this->assertSame(1_000, $coupon->discountOn(Money::fromMajor(100))->toPrimitive());
    }

    public function test_a_coupon_with_a_minimum_waits_until_the_basket_reaches_it(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10, Money::fromMajor(3_000));

        // Measured on the NET subtotal, the same figure the cart labels "excluding VAT".
        // Testing it against the VAT-inclusive total would trigger a "over 3.000" coupon at
        // 2.542 of actual goods.
        $this->assertFalse($coupon->appliesTo(Money::fromMajor(2_999)));
        $this->assertTrue($coupon->appliesTo(Money::fromMajor(3_000)));

        $this->assertTrue($coupon->discountOn(Money::fromMajor(2_999))->isZero());
        $this->assertSame(30_000, $coupon->discountOn(Money::fromMajor(3_000))->toPrimitive());
    }

    public function test_a_below_minimum_code_says_how_much_more_is_needed(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10, Money::fromMajor(3_000));

        $reason = $coupon->reasonItCannotApply(Money::fromMajor(2_600));

        // Dropping the code silently would leave a shopper who typed a valid code staring at
        // an unchanged total with no explanation.
        $this->assertNotNull($reason);
        $this->assertStringContainsString('400', $reason);
    }

    public function test_the_discount_comes_off_the_goods_and_vat_is_charged_on_what_remains(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->withSession([AppliedCoupon::SESSION_KEY => $coupon->code]);

        $html = $this->addToCart($product, 1)->get('/cart')->assertOk()->getContent();

        /*
         | 1.000,00 net, 10% off = 100,00 discount, so 900,00 of goods.
         | VAT at 18% on 900,00 = 162,00, NOT the 180,00 the pre-discount subtotal would give.
         | Charging VAT on the undiscounted figure would have the shop paying tax on 18 ден it
         | never received, on every discounted order.
         |
         | The page shows 196,20, because 900,00 is under the free-delivery threshold so 190,00
         | of delivery is charged and delivery carries its own 34,20 of VAT. My first version of
         | this assertion looked for a bare 162,00 and failed — forgetting delivery VAT is the
         | exact mistake an earlier checkout test was written to catch, and I made it again in
         | the test rather than the code.
        */
        $this->assertStringContainsString('100,00', $html, 'The discount is not shown.');
        $this->assertStringContainsString('900,00', $html, 'The discounted goods total is not shown.');
        $this->assertStringContainsString('196,20', $html,
            'VAT should be 162,00 on the discounted goods plus 34,20 on delivery.');
    }

    public function test_the_receipt_records_the_discount_and_the_code(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(25);

        $receipt = $this->checkoutWith($product, 1, $coupon);

        $this->assertSame(100_000, $receipt->subtotal_minor->toPrimitive());
        $this->assertSame(25_000, $receipt->discount_minor->toPrimitive());
        $this->assertSame($coupon->code, $receipt->coupon_code);

        // 750,00 of goods, VAT 135,00, free delivery over 3.000 not met so 190,00 charged
        // plus its own 34,20 of VAT.
        $this->assertSame(13_500 + 3_420, $receipt->vat_minor->toPrimitive());
        $this->assertSame(19_000, $receipt->shipping_minor->toPrimitive());
        $this->assertSame(75_000 + 16_920 + 19_000, $receipt->total_minor->toPrimitive());
    }

    public function test_the_cart_total_and_the_receipt_total_agree(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(15);

        $this->withSession([AppliedCoupon::SESSION_KEY => $coupon->code]);
        $this->addToCart($product, 2);

        $shown = $this->get('/cart')->assertOk()->getContent();

        $receipt = app(PlaceReceiptAction::class)->execute(
            Basket::query()->latest('id')->firstOrFail()->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje'),
        );

        // The figure on the page is the figure charged. A cart and a receipt disagreeing about
        // a total is the bug the delivery rule was consolidated to prevent, and a discount is
        // the same shape of risk.
        $this->assertStringContainsString($receipt->total_minor->format(), $shown);
    }

    public function test_free_delivery_is_judged_on_the_discounted_amount(): void
    {
        $product = $this->product(priceMajor: 3_100, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $receipt = $this->checkoutWith($product, 1, $coupon);

        /*
         | 3.100 of goods clears the 3.000 free-delivery threshold — but 10% off leaves 2.790
         | actually spent, so delivery is charged. "Free over 3.000" means 3.000 spent.
         |
         | Worth knowing this is a decision, not a law: judging it on the pre-discount subtotal
         | would be defensible too. Flagged for Stefan.
        */
        $this->assertSame(19_000, $receipt->shipping_minor->toPrimitive());
    }

    public function test_a_deactivated_code_stops_discounting_immediately(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->withSession([AppliedCoupon::SESSION_KEY => $coupon->code]);
        $this->addToCart($product, 1);

        $this->get('/cart')->assertOk()->assertSee($coupon->code, false);

        // Switched off by staff while it sits in somebody's session.
        $coupon->update(['is_active' => false]);

        $receipt = app(PlaceReceiptAction::class)->execute(
            Basket::query()->latest('id')->firstOrFail()->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje'),
        );

        $this->assertTrue($receipt->discount_minor->isZero());
        $this->assertNull($receipt->coupon_code);
    }

    public function test_checkout_recomputes_the_discount_rather_than_trusting_the_cart(): void
    {
        $product = $this->product(priceMajor: 2_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10, Money::fromMajor(3_000));

        $this->withSession([AppliedCoupon::SESSION_KEY => $coupon->code]);

        // Two of them clears the minimum, so the cart shows a discount.
        $this->addToCart($product, 2);
        $this->get('/cart')->assertOk();

        // The shopper then removes one, dropping below the minimum.
        $line = BasketLine::query()->latest('id')->firstOrFail();
        $this->post("/cart/{$line->id}", ['quantity' => 1]);

        $receipt = app(PlaceReceiptAction::class)->execute(
            Basket::query()->latest('id')->firstOrFail()->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje'),
        );

        // A code that stopped qualifying must not still take money off.
        $this->assertTrue($receipt->discount_minor->isZero());
    }

    public function test_the_used_counter_only_counts_orders_it_discounted(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 20);
        $coupon = app(GenerateCouponAction::class)->execute(10, Money::fromMajor(5_000));

        // Applied, but the basket never reaches the minimum.
        $this->checkoutWith($product, 1, $coupon);
        $this->assertSame(0, $coupon->fresh()->times_used, 'A code that discounted nothing was counted.');

        // Now a basket that does.
        $this->checkoutWith($this->product(priceMajor: 6_000, stock: 5), 1, $coupon);
        $this->assertSame(1, $coupon->fresh()->times_used);
    }

    public function test_the_discount_on_a_receipt_cannot_be_rewritten(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $receipt = $this->checkoutWith($product, 1, app(GenerateCouponAction::class)->execute(10));

        $this->expectException(ReceiptIsSealedException::class);

        // The discount is a money fact. Leaving it writable would let somebody change what a
        // customer was charged after the fact.
        $receipt->update(['discount_minor' => 0]);
    }

    public function test_staff_can_generate_a_coupon_from_the_admin(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/coupons', ['discount_percent' => 15, 'minimum_order_major' => '3000'])
            ->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::query()->firstOrFail();

        $this->assertSame(15, $coupon->discount_percent);
        $this->assertSame(300_000, $coupon->minimum_order_minor->toPrimitive());
        $this->assertTrue($coupon->is_active);
    }

    public function test_a_zero_minimum_is_stored_as_no_minimum(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/coupons', ['discount_percent' => 10, 'minimum_order_major' => '0']);

        // Otherwise the coupon would describe itself as "off orders over 0,00 ден", which is
        // noise pretending to be a rule.
        $this->assertFalse(Coupon::query()->firstOrFail()->hasMinimum());
    }

    public function test_a_used_coupon_is_switched_off_rather_than_deleted(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $this->checkoutWith($product, 1, $coupon);

        $this->assertSame(1, $coupon->fresh()->times_used);

        $this->actingAs($this->admin())
            ->delete("/admin/coupons/{$coupon->id}")
            ->assertSessionHas('error');

        // Receipts snapshot the code, so deleting would not corrupt an order — but it would
        // destroy the only record of what produced those discounts.
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_an_unused_coupon_can_be_deleted(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->actingAs($this->admin())
            ->delete("/admin/coupons/{$coupon->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_applying_an_unknown_code_says_so_without_leaking_which(): void
    {
        $retired = app(GenerateCouponAction::class)->execute(10);
        $retired->update(['is_active' => false]);

        $never = $this->post('/cart/coupon', ['code' => 'SAVE10ZZZZ']);
        $off = $this->post('/cart/coupon', ['code' => $retired->code]);

        // The same message either way: telling them apart lets somebody probe for retired
        // codes.
        $this->assertSame(
            $never->getSession()->get('coupon_error'),
            $off->getSession()->get('coupon_error'),
        );
    }

    public function test_a_guest_can_apply_and_remove_a_code(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->addToCart($product, 1);

        $this->post('/cart/coupon', ['code' => strtolower($coupon->code)])->assertRedirect();
        // Lowercase in, matched anyway: a code gets typed by hand.
        $this->assertSame($coupon->code, session(AppliedCoupon::SESSION_KEY));

        $this->get('/cart')->assertOk()->assertSee('100,00', false);

        $this->delete('/cart/coupon')->assertRedirect();
        $this->assertNull(session(AppliedCoupon::SESSION_KEY));
    }

    public function test_a_guest_cannot_reach_the_coupon_admin(): void
    {
        $this->get('/admin/coupons')->assertRedirect('/admin/login');
        $this->post('/admin/coupons', ['discount_percent' => 90])->assertRedirect('/admin/login');

        $this->assertSame(0, Coupon::query()->count());
    }

    public function test_the_top_bar_shows_the_free_delivery_threshold_when_nothing_is_promoted(): void
    {
        app(GenerateCouponAction::class)->execute(10);

        // A live code that has NOT been ticked must not appear. Conflating "usable" with
        // "advertised" would put every code you create on the homepage.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('FREE DELIVERY', $html);
        $this->assertStringContainsString('3.000,00 ден', $html);
    }

    public function test_a_promoted_coupon_replaces_the_free_delivery_message(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(20, Money::fromMajor(5_000));
        $coupon->update(['show_as_promotion' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('20% OFF', $html);
        $this->assertStringContainsString($coupon->code, $html);
        // The condition has to travel with the offer, or the bar promises 20% off anything.
        $this->assertStringContainsString('5.000,00 ден', $html);
        $this->assertStringNotContainsString('FREE DELIVERY', $html);
    }

    public function test_a_promoted_coupon_with_no_minimum_says_any_order(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $coupon->update(['show_as_promotion' => true]);

        $this->get('/')->assertOk()->assertSee('on any order with code', false);
    }

    public function test_switching_a_promoted_coupon_off_stops_advertising_it(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(20);
        $coupon->update(['show_as_promotion' => true]);

        $this->get('/')->assertOk()->assertSee($coupon->code, false);

        $coupon->update(['is_active' => false]);

        /*
         | Advertising a code that has been switched off is the same class of lie as the theme's
         | "use code Brator50" for a code that never existed — worse, because a shopper would
         | type it and be told it is invalid.
        */
        $this->get('/')->assertOk()
            ->assertDontSee($coupon->code, false)
            ->assertSee('FREE DELIVERY', false);
    }

    public function test_the_newest_promoted_coupon_is_the_one_shown(): void
    {
        $old = app(GenerateCouponAction::class)->execute(5);
        $old->update(['show_as_promotion' => true]);
        // Set directly, not mass-assigned: created_at is deliberately not fillable, and it
        // should stay that way — a timestamp somebody can post is a timestamp that lies.
        $old->created_at = now()->subDay();
        $old->save();

        $new = app(GenerateCouponAction::class)->execute(25);
        $new->update(['show_as_promotion' => true]);

        // The bar is one line of the theme's markup, so ticking a second code takes over rather
        // than stacking. Predictable beats clever: the campaign you just set is the one running.
        $this->get('/')->assertOk()
            ->assertSee($new->code, false)
            ->assertDontSee($old->code, false);
    }

    public function test_staff_can_generate_a_coupon_already_promoted(): void
    {
        $this->actingAs($this->admin())->post('/admin/coupons', [
            'discount_percent' => 20,
            'show_as_promotion' => 1,
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::query()->firstOrFail();

        $this->assertTrue($coupon->show_as_promotion);
        $this->get('/')->assertOk()->assertSee($coupon->code, false);
    }

    public function test_toggling_the_top_bar_does_not_disturb_whether_the_code_is_live(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);

        $this->actingAs($this->admin())
            ->put("/admin/coupons/{$coupon->id}", ['show_as_promotion' => 1])
            ->assertRedirect();

        $coupon->refresh();

        // The two switches are independent. A promote click that silently deactivated the code
        // would be a nasty surprise.
        $this->assertTrue($coupon->show_as_promotion);
        $this->assertTrue($coupon->is_active, 'Promoting a code must not change whether it is live.');
    }

    public function test_toggling_live_does_not_disturb_the_top_bar_flag(): void
    {
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $coupon->update(['show_as_promotion' => true]);

        $this->actingAs($this->admin())
            ->put("/admin/coupons/{$coupon->id}", ['is_active' => 0])
            ->assertRedirect();

        $coupon->refresh();

        $this->assertFalse($coupon->is_active);
        // Still ticked, so switching it back on resumes advertising without a second click.
        $this->assertTrue($coupon->show_as_promotion);
    }

    public function test_the_promoted_code_in_the_bar_actually_works(): void
    {
        $product = $this->product(priceMajor: 1_000, stock: 10);
        $coupon = app(GenerateCouponAction::class)->execute(10);
        $coupon->update(['show_as_promotion' => true]);

        // Read the code off the page the way a shopper would, then spend it. The bar promising a
        // code that does not work is the failure this whole feature exists to avoid.
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/SAVE10[A-Z0-9]{4}/', $html, $m);
        $this->assertNotEmpty($m, 'No code found in the top bar.');

        $this->addToCart($product, 1);
        $this->post('/cart/coupon', ['code' => $m[0]])->assertSessionMissing('coupon_error');

        $this->get('/cart')->assertOk()->assertSee('100,00', false);
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

    private function addToCart(Product $product, int $quantity): self
    {
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertRedirect();

        return $this;
    }

    private function checkoutWith(Product $product, int $quantity, Coupon $coupon)
    {
        $basket = Basket::create(['session_token' => (string) Str::ulid()]);

        BasketLine::create([
            'basket_id' => $basket->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price_minor' => $product->price_minor->toPrimitive(),
        ]);

        session([AppliedCoupon::SESSION_KEY => $coupon->code]);

        return app(PlaceReceiptAction::class)->execute(
            $basket->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje'),
        );
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
