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
            ->assertSee($product->name, false)
            ->assertSee($product->sku, false);
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
        $product = $this->buyable();
        $this->post('/cart/add', ['product_id' => $product->id]);
        $lineId = DB::table('basket_lines')->value('id');

        // A different visitor — new session, so no basket. Posting someone else's line
        // id must not edit or delete their cart.
        $this->flushSession();

        $this->post("/cart/{$lineId}", ['quantity' => 99])->assertNotFound();
        $this->delete("/cart/{$lineId}")->assertNotFound();

        $this->assertSame(1, DB::table('basket_lines')->count());
        $this->assertSame(1, (int) DB::table('basket_lines')->value('quantity'));
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
            ->assertSee($receipt->receipt_number, false)
            ->assertSee($product->name, false)
            ->assertSee('Ana', false);
    }

    private function buyable(): Product
    {
        return Product::query()
            ->where('stock_status', StockStatus::InStock)
            ->firstOrFail();
    }
}
