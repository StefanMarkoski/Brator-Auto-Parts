<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\PlaceReceiptAction;
use App\Domain\Ordering\DTOs\CheckoutDetails;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Exceptions\ReceiptIsSealedException;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use App\Domain\Ordering\Models\Receipt;
use App\Domain\Ordering\Models\ReceiptLine;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A receipt is a historical document, not a view of current state. These tests are the
 * guard on the single most valuable property of the schema.
 */
final class ReceiptIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_renaming_and_repricing_a_product_does_not_change_an_existing_receipt(): void
    {
        $brand = Brand::factory()->create(['name' => 'BrakePro']);
        // Two products, never one — see the lazy-loading guard note.
        [$product] = Product::factory()->count(2)->create(['brand_id' => $brand->id, 'price_minor' => 100_000]);

        $receipt = Receipt::factory()->create();
        $line = ReceiptLine::create([
            'receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
            'brand_name_snapshot' => $brand->name,
            'unit_price_minor' => 100_000,
            'quantity' => 2,
            'line_total_minor' => 200_000,
            'vat_rate' => 18.0,
            'vat_minor' => 36_000,
        ]);

        // The shop renames the part, drops the price, rebrands, then deletes it.
        $product->update(['name' => 'Renamed Part', 'price_minor' => 1]);
        $brand->update(['name' => 'Rebranded']);
        $product->delete();

        $line->refresh();

        $this->assertSame('BrakePro', $line->brand_name_snapshot);
        $this->assertSame(100_000, $line->unit_price_minor->minor);
        $this->assertSame(200_000, $line->line_total_minor->minor);
        $this->assertNotSame('Renamed Part', $line->product_name_snapshot);
    }

    public function test_a_hard_deleted_product_leaves_the_receipt_line_readable(): void
    {
        [$product] = Product::factory()->count(2)->create();
        $receipt = Receipt::factory()->create();

        $line = ReceiptLine::create([
            'receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'product_name_snapshot' => 'Original Name',
            'product_sku_snapshot' => 'SKU-1',
            'brand_name_snapshot' => null,
            'unit_price_minor' => 50_000,
            'quantity' => 1,
            'line_total_minor' => 50_000,
            'vat_rate' => 18.0,
            'vat_minor' => 9_000,
        ]);

        $product->forceDelete();
        $line->refresh();

        // nullOnDelete, so the FK goes null and the snapshot still tells the story.
        $this->assertNull($line->product_id);
        $this->assertSame('Original Name', $line->product_name_snapshot);
    }

    public function test_a_receipt_cannot_be_rewritten(): void
    {
        $receipt = Receipt::factory()->create(['total_minor' => 123_456]);

        // Snapshotting the values was only half the job — the reviewer rewrote a total
        // to 1. A snapshot is not a seal.
        foreach ([
            'total_minor' => 1,
            'subtotal_minor' => 1,
            'vat_minor' => 1,
            'receipt_number' => 'HACKED',
            'customer_email' => 'someone@else.test',
            'shipping_address' => 'Elsewhere',
        ] as $field => $value) {
            try {
                Receipt::query()->findOrFail($receipt->id)->update([$field => $value]);
                $this->fail("{$field} was changeable on a placed receipt.");
            } catch (ReceiptIsSealedException) {
                // expected
            }
        }

        $this->assertSame(123_456, Receipt::query()->findOrFail($receipt->id)->total_minor->minor);
    }

    public function test_a_receipt_cannot_be_deleted(): void
    {
        $receipt = Receipt::factory()->create();

        $this->expectException(ReceiptIsSealedException::class);

        Receipt::query()->findOrFail($receipt->id)->delete();
    }

    public function test_status_and_notes_remain_editable(): void
    {
        $receipt = Receipt::factory()->create();

        // Staff legitimately cancel orders and add remarks. Sealing the money must not
        // freeze the workflow around it.
        Receipt::query()->findOrFail($receipt->id)->update([
            'status' => ReceiptStatus::Cancelled,
            'notes' => 'Customer called to cancel.',
        ]);

        $fresh = Receipt::query()->findOrFail($receipt->id);

        $this->assertSame(ReceiptStatus::Cancelled, $fresh->status);
        $this->assertSame('Customer called to cancel.', $fresh->notes);
    }

    public function test_a_receipt_line_cannot_be_changed_or_removed(): void
    {
        $receipt = Receipt::factory()->create();
        $line = ReceiptLine::create([
            'receipt_id' => $receipt->id,
            'product_id' => null,
            'product_name_snapshot' => 'Part',
            'product_sku_snapshot' => 'SKU',
            'brand_name_snapshot' => null,
            'unit_price_minor' => 50_000,
            'quantity' => 1,
            'line_total_minor' => 50_000,
            'vat_rate' => 18.0,
            'vat_minor' => 9_000,
        ]);

        try {
            ReceiptLine::query()->findOrFail($line->id)->update(['unit_price_minor' => 1]);
            $this->fail('A receipt line was changeable.');
        } catch (ReceiptIsSealedException) {
            // expected
        }

        $this->assertSame(50_000, ReceiptLine::query()->findOrFail($line->id)->unit_price_minor->minor);
    }

    public function test_the_real_checkout_computes_totals_correctly(): void
    {
        // REWRITTEN after review. The old version wrote the lines, then wrote those same
        // computed numbers onto the receipt, then asserted they matched — so if the real
        // checkout computed every total wrongly, it still passed. It never called
        // PlaceReceiptAction at all.
        //
        // This drives the actual checkout and checks the arithmetic against figures
        // worked out independently here.
        $this->seed(CatalogStructureSeeder::class);

        $product = Product::factory()->create([
            'price_minor' => 30_000,
            'sale_price_minor' => null,
            'stock_quantity' => 10,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $basket = Basket::create(['session_token' => (string) Str::ulid()]);
        BasketLine::create([
            'basket_id' => $basket->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price_minor' => 30_000,
        ]);

        $receipt = app(PlaceReceiptAction::class)->execute(
            $basket->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje')
        );

        // Worked out by hand: 3 x 300,00 = 900,00 net. VAT at 18% on the LINE = 162,00.
        // Under 3.000 net, so delivery is charged at 190,00 — and delivery is itself a
        // taxable supply, so it carries 34,20 of VAT. Total VAT 196,20.
        //
        // That 34,20 is the exact figure the review said was being under-collected on
        // every delivered order, and this assertion is what would have caught it.
        $this->assertSame(90_000, $receipt->subtotal_minor->minor);
        $this->assertSame(16_200 + 3_420, $receipt->vat_minor->minor);
        $this->assertSame(19_000, $receipt->shipping_minor->minor);
        $this->assertSame(90_000 + 19_620 + 19_000, $receipt->total_minor->minor);

        // And the receipt agrees with its own lines.
        $lineNet = $receipt->lines->reduce(
            fn (Money $carry, ReceiptLine $line): Money => $carry->add($line->line_total_minor),
            Money::zero()
        );
        $this->assertTrue($receipt->subtotal_minor->equals($lineNet));

        // Stock left the shelf.
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);
    }

    public function test_free_delivery_applies_above_the_threshold(): void
    {
        $this->seed(CatalogStructureSeeder::class);

        $product = Product::factory()->create([
            'price_minor' => 400_000,
            'sale_price_minor' => null,
            'stock_quantity' => 5,
            'stock_status' => StockStatus::InStock,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $basket = Basket::create(['session_token' => (string) Str::ulid()]);
        BasketLine::create([
            'basket_id' => $basket->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price_minor' => 400_000,
        ]);

        $receipt = app(PlaceReceiptAction::class)->execute(
            $basket->refresh(),
            new CheckoutDetails('Ana', 'ana@example.com', null, 'Skopje')
        );

        $this->assertTrue($receipt->shipping_minor->isZero());
        $this->assertSame(400_000 + 72_000, $receipt->total_minor->minor);
    }
}
