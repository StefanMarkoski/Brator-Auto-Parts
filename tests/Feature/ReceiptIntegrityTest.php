<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\Receipt;
use App\Domain\Ordering\Models\ReceiptLine;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_receipt_totals_agree_with_their_own_lines(): void
    {
        $this->seed(CatalogStructureSeeder::class);

        $receipt = Receipt::factory()->create();
        $expectedNet = Money::zero();
        $expectedVat = Money::zero();

        foreach ([[30_000, 2], [12_345, 3], [999, 1]] as [$unit, $qty]) {
            $lineTotal = Money::fromMinor($unit)->timesQuantity($qty);
            $lineVat = $lineTotal->vatAt(18);
            $expectedNet = $expectedNet->add($lineTotal);
            $expectedVat = $expectedVat->add($lineVat);

            ReceiptLine::create([
                'receipt_id' => $receipt->id,
                'product_id' => null,
                'product_name_snapshot' => 'Part',
                'product_sku_snapshot' => 'SKU',
                'brand_name_snapshot' => null,
                'unit_price_minor' => $unit,
                'quantity' => $qty,
                'line_total_minor' => $lineTotal->toPrimitive(),
                'vat_rate' => 18.0,
                'vat_minor' => $lineVat->toPrimitive(),
            ]);
        }

        $receipt->update([
            'subtotal_minor' => $expectedNet->toPrimitive(),
            'vat_minor' => $expectedVat->toPrimitive(),
            'total_minor' => $expectedNet->add($expectedVat)->toPrimitive(),
        ]);
        $receipt->refresh()->load('lines');

        $lineNet = $receipt->lines->reduce(
            fn (Money $c, ReceiptLine $l): Money => $c->add($l->line_total_minor),
            Money::zero()
        );
        $lineVat = $receipt->lines->reduce(
            fn (Money $c, ReceiptLine $l): Money => $c->add($l->vat_minor),
            Money::zero()
        );

        $this->assertTrue($receipt->subtotal_minor->equals($lineNet));
        $this->assertTrue($receipt->vat_minor->equals($lineVat));
        $this->assertTrue($receipt->total_minor->equals($lineNet->add($lineVat)));
    }
}
