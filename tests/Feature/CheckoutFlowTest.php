<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Mail\ReceiptPlacedMail;
use App\Domain\Ordering\Models\Receipt;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Basket to receipt, end to end. The payment is fake; everything around it is not,
 * so this asserts the money, the stock ledger and the email.
 */
final class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    public function test_a_visitor_can_add_a_part_and_see_it_in_the_cart(): void
    {
        $product = $this->buyable();

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect(route('cart'));

        $this->get('/cart')->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->sku);
    }

    public function test_adding_the_same_part_twice_increases_the_quantity(): void
    {
        $product = $this->buyable();

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 3]);

        $this->assertSame(1, DB::table('basket_lines')->count(), 'A second add should not create a second line.');
        $this->assertSame(4, (int) DB::table('basket_lines')->value('quantity'));
    }

    public function test_the_posted_price_is_ignored(): void
    {
        $product = $this->buyable();

        // A price in the request body is a suggestion from a stranger.
        $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price_minor' => 1,
            'price' => 1,
        ])->assertRedirect(route('cart'));

        $stored = (int) DB::table('basket_lines')->value('unit_price_minor');
        $expected = ($product->sale_price_minor ?? $product->price_minor)->toPrimitive();

        $this->assertSame($expected, $stored);
    }

    public function test_an_out_of_stock_part_cannot_be_added(): void
    {
        $product = $this->buyable();
        $product->update(['stock_status' => StockStatus::OutOfStock]);

        $this->post('/cart/add', ['product_id' => $product->id])->assertRedirect();

        $this->assertSame(0, DB::table('basket_lines')->count());
    }

    public function test_a_line_can_be_updated_and_removed(): void
    {
        $product = $this->buyable();
        $this->post('/cart/add', ['product_id' => $product->id]);
        $lineId = DB::table('basket_lines')->value('id');

        $this->post("/cart/{$lineId}", ['quantity' => 5])->assertRedirect(route('cart'));
        $this->assertSame(5, (int) DB::table('basket_lines')->value('quantity'));

        $this->delete("/cart/{$lineId}")->assertRedirect(route('cart'));
        $this->assertSame(0, DB::table('basket_lines')->count());
    }

    public function test_a_visitor_cannot_touch_a_line_that_is_not_in_their_basket(): void
    {
        // REWRITTEN after review. The old version called flushSession(), which meant the
        // request exited at the "no basket at all" guard and never reached the
        // basket_id comparison — delete the ownership check and it stayed green.
        //
        // This gives the second visitor a basket of their OWN, so the only thing that
        // can stop them touching someone else's line is the ownership check itself.
        $productA = $this->buyable();
        $this->post('/cart/add', ['product_id' => $productA->id]);
        $victimLineId = DB::table('basket_lines')->value('id');
        $victimBasketId = DB::table('baskets')->value('id');

        // A different visitor, with their own basket.
        $this->flushSession();
        $this->post('/cart/add', ['product_id' => $productA->id]);

        $attackerBasketId = DB::table('baskets')
            ->where('id', '!=', $victimBasketId)->value('id');
        $this->assertNotNull($attackerBasketId, 'The second visitor should have their own basket.');

        // Now posting the victim's line id must 404 rather than edit or delete it.
        $this->post("/cart/{$victimLineId}", ['quantity' => 99])->assertNotFound();
        $this->delete("/cart/{$victimLineId}")->assertNotFound();

        $victimLine = DB::table('basket_lines')->where('id', $victimLineId)->first();
        $this->assertNotNull($victimLine, "The victim's line was deleted by another visitor.");
        $this->assertSame(1, (int) $victimLine->quantity, "The victim's quantity was changed.");
    }

    public function test_checkout_produces_a_correct_receipt_and_emails_it(): void
    {
        Mail::fake();

        $product = $this->buyable();
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 3]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Stefan Markoski',
            'customer_email' => 'stefan.m@xgate.io',
            'customer_phone' => '+389 70 123456',
            'shipping_address' => "ul. Partizanska 12\nSkopje",
        ]);

        $receipt = Receipt::query()->with('lines')->firstOrFail();
        $response->assertRedirect(route('receipt', $receipt->id));

        $this->assertSame(ReceiptStatus::Paid, $receipt->status);
        $this->assertMatchesRegularExpression('/^BR-\d{4}-\d{6}$/', $receipt->receipt_number);

        // The money: VAT per line, then summed, and the total is net + VAT + delivery.
        $unit = $product->sale_price_minor ?? $product->price_minor;
        $expectedNet = $unit->timesQuantity(3);
        $expectedVat = $expectedNet->vatAt((float) config('shop.vat_rate'));

        $this->assertTrue($receipt->subtotal_minor->equals($expectedNet));
        $this->assertTrue($receipt->vat_minor->equals($expectedVat));
        $this->assertTrue($receipt->total_minor->equals(
            $expectedNet->add($expectedVat)->add($receipt->shipping_minor)
        ));

        // Snapshots, so the receipt survives the product changing.
        $line = $receipt->lines->first();
        $this->assertSame($product->name, $line->product_name_snapshot);
        $this->assertSame($product->sku, $line->product_sku_snapshot);

        // Stock left the shelf, and the ledger agrees with the cached quantity.
        $this->assertSame(-3, (int) DB::table('stock_movements')
            ->where('product_id', $product->id)->where('reason', 'sale')->value('delta'));

        // The basket is emptied — a placed order must not still be in the cart.
        $this->assertSame(0, DB::table('basket_lines')->count());

        Mail::assertSent(ReceiptPlacedMail::class, fn ($mail) => $mail->hasTo('stefan.m@xgate.io'));
    }

    public function test_a_price_change_stops_checkout_instead_of_charging_the_new_price(): void
    {
        $product = $this->buyable();
        $product->update(['sale_price_minor' => null, 'price_minor' => 100_000]);

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        // The shop triples the price while it sits in the cart.
        $product->update(['price_minor' => 300_000]);

        $this->post('/checkout', [
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'shipping_address' => 'Skopje',
        ])->assertRedirect(route('cart'));

        // Nothing is sold, and nobody is charged a price they never saw. The first
        // version of this code silently substituted the new price — cart showed 1.000,
        // receipt charged 3.000.
        $this->assertSame(0, Receipt::query()->count());
        $this->assertStringContainsString('changed from', (string) session('error'));

        // The cart is brought up to the live price, so a retry is not the same wall.
        $this->assertSame(300_000, (int) DB::table('basket_lines')->value('unit_price_minor'));
    }

    public function test_an_unchanged_price_checks_out_at_the_price_shown(): void
    {
        $product = $this->buyable();
        $product->update(['sale_price_minor' => null, 'price_minor' => 100_000]);

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);
        $this->post('/checkout', [
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'shipping_address' => 'Skopje',
        ]);

        $line = Receipt::query()->with('lines')->firstOrFail()->lines->first();

        $this->assertSame(100_000, $line->unit_price_minor->minor);
        $this->assertSame(200_000, $line->line_total_minor->minor);
    }

    public function test_checkout_rejects_an_empty_basket(): void
    {
        $this->post('/checkout', [
            'customer_name' => 'Nobody',
            'customer_email' => 'nobody@example.com',
            'shipping_address' => 'Somewhere',
        ])->assertRedirect(route('cart'));

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_checkout_validates_the_customer_details(): void
    {
        $this->post('/cart/add', ['product_id' => $this->buyable()->id]);

        $this->post('/checkout', ['customer_email' => 'not-an-email'])
            ->assertSessionHasErrors(['customer_name', 'customer_email', 'shipping_address']);

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_receipt_numbers_are_sequential(): void
    {
        $product = $this->buyable();

        foreach (range(1, 3) as $n) {
            $this->post('/cart/add', ['product_id' => $product->id]);
            $this->post('/checkout', [
                'customer_name' => "Customer {$n}",
                'customer_email' => "c{$n}@example.com",
                'shipping_address' => 'Skopje',
            ]);
        }

        $numbers = Receipt::query()->orderBy('receipt_number')->pluck('receipt_number')->all();
        $year = now()->format('Y');

        $this->assertSame([
            "BR-{$year}-000001", "BR-{$year}-000002", "BR-{$year}-000003",
        ], $numbers);
    }

    public function test_the_receipt_page_shows_the_order(): void
    {
        $product = $this->buyable();
        $this->post('/cart/add', ['product_id' => $product->id]);
        $this->post('/checkout', [
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'shipping_address' => 'Skopje',
        ]);

        $receipt = Receipt::query()->firstOrFail();

        $this->get(route('receipt', $receipt->id))
            ->assertOk()
            ->assertSee($receipt->receipt_number)
            ->assertSee($product->name)
            ->assertSee('Ana', false);
    }

    public function test_the_checkout_form_carries_the_double_submit_hook(): void
    {
        // BE CLEAR ABOUT WHAT THIS DOES NOT DO. The double-submit guard is JavaScript —
        // PHPUnit never runs it, so nothing here proves that a second click is swallowed
        // or that the button is re-enabled coming back from the back/forward cache. That
        // is verified in a browser.
        //
        // What it does pin is the contract storefront.js binds to: bindSubmitOnce finds
        // its forms by data-submit-once and takes its busy text from
        // data-submit-once-label. Drop either attribute while editing this template and
        // the guard is still there, bound to nothing, silently. Asserted against the
        // checkout action so it cannot pass by matching some other form on the page.
        $this->post('/cart/add', ['product_id' => $this->buyable()->id]);

        $this->get('/cart')->assertOk()->assertSee(
            'action="'.route('checkout.place', [], false).'" data-submit-once data-submit-once-label="Placing your order…"',
            false
        );
    }

    /**
     * A product with pinned, known values.
     *
     * Every fixture here used to be "whatever came back first", which made several of
     * these tests pass or fail depending on the random seed — sale price present or not,
     * stock high or low. A test that depends on the seed is a test you cannot trust, and
     * the reviewer was right to say so.
     */
    private function buyable(int $stock = 50): Product
    {
        $product = Product::query()
            ->where('stock_status', StockStatus::InStock)
            ->firstOrFail();

        $product->update([
            'sale_price_minor' => null,
            'price_minor' => 100_000,
            'stock_quantity' => $stock,
            'published_at' => now()->subDay(),
            'is_active' => true,
        ]);

        return $product->refresh();
    }
}
