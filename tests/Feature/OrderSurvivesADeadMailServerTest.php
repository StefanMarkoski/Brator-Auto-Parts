<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Models\Receipt;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * A placed order must survive an unreachable mail server.
 *
 * This is a hosting bug that CANNOT happen locally, which is why it needs a test rather
 * than a manual check: Mailpit never refuses a connection, so every local run is green.
 *
 * The shape of it. SendReceiptEmail runs after PlaceReceiptAction's transaction has
 * committed — receipt written, stock decremented, basket emptied. Symfony's
 * TransportException extends \RuntimeException (vendor/symfony/mailer/Exception), and
 * CheckoutController::place() catches RuntimeException and redirects to the cart with the
 * exception's message. So a dead SMTP host produced the worst available outcome: the order
 * was real and complete, and the shopper was shown an empty cart and an SMTP error, from
 * which the only reasonable conclusion is that it failed and they should order again.
 *
 * WHICH OF THESE ACTUALLY GUARD THE FIX: only the first. Verified by reverting the catch —
 * the first test fails, the other two pass unchanged, because the receipt and the ledger
 * were already committed before the send was ever attempted. The other two are here to
 * pin that ordering, not to catch this regression, and should not be read as if they were.
 */
final class OrderSurvivesADeadMailServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    private function buyable(int $stock = 50): Product
    {
        $product = Product::query()->where('stock_status', StockStatus::InStock)->firstOrFail();

        $product->update([
            'sale_price_minor' => null,
            'price_minor' => 100_000,
            'stock_quantity' => $stock,
            'published_at' => now()->subDay(),
            'is_active' => true,
        ]);

        return $product->refresh();
    }

    /** Every way the mail layer can fail, from the checkout's point of view, is this. */
    private function breakTheMailServer(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(
            new TransportException('Connection could not be established with host "mail:1025"'),
        );
    }

    public function test_the_order_completes_and_lands_on_the_receipt_when_mail_is_dead(): void
    {
        $product = $this->buyable();
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);

        $this->breakTheMailServer();

        $response = $this->post('/checkout', [
            'customer_name' => 'Stefan Markoski',
            'customer_email' => 'stefan.m@xgate.io',
            'customer_phone' => '+389 70 123456',
            'shipping_address' => "ul. Partizanska 12\nSkopje",
        ]);

        $receipt = Receipt::query()->firstOrFail();

        // The load-bearing assertion. Before the fix this was a redirect to the cart
        // carrying the SMTP error in the session.
        $response->assertRedirect(route('receipt', $receipt->id));
        $this->assertNull(session('error'), 'The shopper was shown an error for an order that succeeded.');

        $this->assertSame(ReceiptStatus::Paid, $receipt->status);
    }

    public function test_the_receipt_page_still_renders_so_the_shopper_has_their_confirmation(): void
    {
        $product = $this->buyable();
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $this->breakTheMailServer();

        $this->post('/checkout', [
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'shipping_address' => 'Skopje',
        ]);

        $receipt = Receipt::query()->firstOrFail();

        // The email is the stated deliverable of the dummy checkout, so when it cannot be
        // sent the on-screen receipt is the only thing the shopper walks away with.
        $this->get(route('receipt', $receipt->id))
            ->assertOk()
            ->assertSee($receipt->receipt_number);
    }

    public function test_the_ledger_is_not_left_disagreeing_with_itself(): void
    {
        $product = $this->buyable(50);
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 4]);

        $this->breakTheMailServer();

        $this->post('/checkout', [
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'shipping_address' => 'Skopje',
        ]);

        // Stock really moved, the sale really recorded, and the basket really emptied —
        // the three things that were already true before the email was attempted. A failed
        // send must not roll any of them back, and must not leave them half-done.
        $this->assertSame(46, (int) $product->refresh()->stock_quantity);
        $this->assertSame(1, DB::table('stock_movements')->where('reason', 'sale')->count());
        $this->assertSame(0, DB::table('basket_lines')->count());
    }
}
