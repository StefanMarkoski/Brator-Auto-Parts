<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\PlaceReceiptAction;
use App\Domain\Ordering\DTOs\CheckoutDetails;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use App\Domain\Ordering\Models\Receipt;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Receipt status transitions and what they do to the shelf.
 *
 * Every test here places the order through the REAL checkout rather than fixturing a
 * receipt, because the thing under test is the relationship between a receipt and stock —
 * and a hand-written receipt has no stock history to unwind. The previous generation of
 * checkout tests wrote their own expected numbers and asserted they matched; driving the
 * real path is the only version that can fail for the right reason.
 */
final class ReceiptStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogStructureSeeder::class);
    }

    public function test_cancelling_a_paid_receipt_puts_the_stock_back(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 3, stock: 10);

        $this->assertSame(7, (int) $product->fresh()->stock_quantity);

        $this->actingAs($this->admin())
            ->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled'])
            ->assertRedirect(route('admin.receipts.show', $receipt->id));

        $this->assertSame(ReceiptStatus::Cancelled, $receipt->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock_quantity);

        // And the return is on the ledger, attributed to this receipt — a quantity that
        // changed with nothing to explain it is a quantity nobody can reconcile later.
        $movement = DB::table('stock_movements')
            ->where('reference_id', $receipt->id)
            ->where('reason', 'cancellation')
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(3, (int) $movement->delta);
    }

    public function test_cancelling_twice_does_not_credit_the_stock_twice(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 4, stock: 10);

        // The double-clicked button. Idempotence comes from reading the stored status under
        // a row lock: by the time the second request looks, the receipt already says
        // cancelled and there is nothing to do.
        foreach (range(1, 3) as $ignored) {
            $this->actingAs($this->admin())
                ->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled']);
        }

        $this->assertSame(10, (int) $product->fresh()->stock_quantity,
            'Stock was credited more than once — a cancelled order returned its goods repeatedly.');

        $this->assertSame(1, DB::table('stock_movements')
            ->where('reference_id', $receipt->id)->where('reason', 'cancellation')->count());
    }

    public function test_reinstating_a_cancelled_receipt_takes_the_stock_back_off(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 2, stock: 5);

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled']);
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'paid']);

        $this->assertSame(ReceiptStatus::Paid, $receipt->fresh()->status);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity,
            'Reinstating an order must commit its goods again, or the shop can sell them twice.');
    }

    public function test_reinstating_is_refused_when_the_stock_has_since_been_sold(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 5, stock: 5);

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled']);
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);

        // Somebody else buys the lot in the meantime.
        $product->fresh()->update(['stock_quantity' => 0, 'stock_status' => StockStatus::OutOfStock]);

        $this->actingAs($this->admin())
            ->put("/admin/receipts/{$receipt->id}/status", ['status' => 'paid'])
            ->assertSessionHas('error');

        // The whole transition rolls back rather than overselling: the receipt is still
        // cancelled and the shelf is still empty.
        $this->assertSame(ReceiptStatus::Cancelled, $receipt->fresh()->status);
        $this->assertSame(0, (int) $product->fresh()->stock_quantity);
    }

    public function test_moving_between_pending_and_paid_leaves_stock_alone(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 3, stock: 10);

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'pending']);
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'paid']);

        // The goods were committed the moment the order existed. Neither of these two
        // states says anything about a shelf, so neither may touch one.
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);
        $this->assertSame(0, DB::table('stock_movements')
            ->where('reference_id', $receipt->id)->where('reason', 'cancellation')->count());
    }

    public function test_cancelling_restocks_a_product_that_had_sold_out(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 4, stock: 4);

        // The order emptied the shelf, so checkout marked it out of stock.
        $this->assertSame(0, (int) $product->fresh()->stock_quantity);
        $this->assertFalse($product->fresh()->stock_status->isBuyable());

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled']);

        // Returning the units has to clear the flag too. Otherwise the part sits at
        // quantity 4 and stays invisible to every shopper, with nothing to explain it.
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
        $this->assertTrue($product->fresh()->stock_status->isBuyable(),
            'A cancellation that refills an empty shelf must make the part buyable again.');
    }

    public function test_cancelling_does_not_change_a_single_figure_on_the_receipt(): void
    {
        [$receipt] = $this->placeOrder(quantity: 3, stock: 10);

        // Compared as integers, not as Money objects: assertSame on two value objects
        // compares identity, so it fails on equal amounts held in different instances.
        $figures = fn (Receipt $r): array => [
            'subtotal' => $r->subtotal_minor->toPrimitive(),
            'vat' => $r->vat_minor->toPrimitive(),
            'shipping' => $r->shipping_minor->toPrimitive(),
            'total' => $r->total_minor->toPrimitive(),
        ];

        $before = $figures($receipt);

        $this->actingAs($this->admin())->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled']);

        // A receipt records what happened. Cancelling it does not retroactively make the
        // order cost nothing, and the seal on those columns would throw if we tried.
        $this->assertSame($before, $figures($receipt->fresh()));
    }

    public function test_a_guest_cannot_change_a_receipt_status(): void
    {
        [$receipt, $product] = $this->placeOrder(quantity: 3, stock: 10);

        $this->put("/admin/receipts/{$receipt->id}/status", ['status' => 'cancelled'])
            ->assertRedirect('/admin/login');

        $this->assertSame(ReceiptStatus::Paid, $receipt->fresh()->status);
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        [$receipt] = $this->placeOrder(quantity: 1, stock: 5);

        $this->actingAs($this->admin())
            ->put("/admin/receipts/{$receipt->id}/status", ['status' => 'refunded'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ReceiptStatus::Paid, $receipt->fresh()->status);
    }

    public function test_staff_can_save_a_note_without_touching_anything_else(): void
    {
        [$receipt] = $this->placeOrder(quantity: 1, stock: 5);

        $this->actingAs($this->admin())
            ->put("/admin/receipts/{$receipt->id}/notes", ['notes' => 'Customer collecting Friday.'])
            ->assertRedirect(route('admin.receipts.show', $receipt->id));

        $this->assertSame('Customer collecting Friday.', $receipt->fresh()->notes);
        $this->assertSame(ReceiptStatus::Paid, $receipt->fresh()->status);
    }

    /**
     * Places a real order through the checkout.
     *
     * @return array{0: Receipt, 1: Product}
     */
    private function placeOrder(int $quantity, int $stock): array
    {
        $product = Product::factory()->create([
            'price_minor' => 30_000,
            'sale_price_minor' => null,
            'stock_quantity' => $stock,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $basket = Basket::create(['session_token' => (string) Str::ulid()]);
        BasketLine::create([
            'basket_id' => $basket->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price_minor' => 30_000,
        ]);

        $receipt = app(PlaceReceiptAction::class)->execute(
            $basket->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje')
        );

        return [$receipt, $product];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
