<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ordering\Support\DeliveryCharge;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Receipts with plausible line combinations, so the bought-together job has real
 * co-occurrence to compute from once it runs.
 *
 * Totals are computed here the same way the real checkout will compute them: VAT per
 * line, rounded half-up, then summed. Seeding a receipt whose total disagrees with its
 * own lines would be worse than seeding nothing.
 */
class ReceiptSeeder extends Seeder
{
    private const RECEIPTS = 500;

    public function run(): void
    {
        $now = now();
        $vatRate = (float) config('shop.vat_rate');

        $products = DB::table('products')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->select('products.id', 'products.sku', 'products.name', 'products.price_minor', 'brands.name as brand_name')
            ->inRandomOrder()
            ->limit(800)
            ->get()
            ->all();

        $receipts = [];
        $lines = [];

        for ($r = 0; $r < self::RECEIPTS; $r++) {
            $receiptId = (string) Str::ulid();
            $lineCount = random_int(1, 4);

            $subtotal = Money::zero();
            $vatTotal = Money::zero();
            $picked = [];

            for ($l = 0; $l < $lineCount; $l++) {
                $product = $products[array_rand($products)];
                if (isset($picked[$product->id])) {
                    continue;
                }
                $picked[$product->id] = true;

                $quantity = random_int(1, 3);
                $unit = Money::fromMinor((int) $product->price_minor);
                $lineTotal = $unit->timesQuantity($quantity);
                // Per line, then summed — never on the order total.
                $lineVat = $lineTotal->vatAt($vatRate);

                $subtotal = $subtotal->add($lineTotal);
                $vatTotal = $vatTotal->add($lineVat);

                $lines[] = [
                    'id' => (string) Str::ulid(),
                    'receipt_id' => $receiptId,
                    'product_id' => $product->id,
                    // Snapshots: this receipt must still read correctly after the
                    // product is renamed, repriced, or deleted.
                    'product_name_snapshot' => $product->name,
                    'product_sku_snapshot' => $product->sku,
                    'brand_name_snapshot' => $product->brand_name,
                    'unit_price_minor' => $unit->toPrimitive(),
                    'quantity' => $quantity,
                    'line_total_minor' => $lineTotal->toPrimitive(),
                    'vat_rate' => $vatRate,
                    'vat_minor' => $lineVat->toPrimitive(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $shipping = DeliveryCharge::for($subtotal);
            $vatTotal = $vatTotal->add(
                DeliveryCharge::vatOn($shipping, $vatRate)
            );
            $total = $subtotal->add($vatTotal)->add($shipping);
            $placedAt = $now->copy()->subDays(random_int(0, 180));

            $receipts[] = [
                'id' => $receiptId,
                'receipt_number' => 'BR-2026-'.str_pad((string) ($r + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => null,
                'customer_name' => 'Customer '.($r + 1),
                'customer_email' => 'customer'.($r + 1).'@example.com',
                'customer_phone' => '+389 7'.random_int(1, 9).' '.random_int(100000, 999999),
                'shipping_address' => 'ul. Partizanska '.random_int(1, 200).', Skopje',
                'billing_address' => null,
                'subtotal_minor' => $subtotal->toPrimitive(),
                'vat_minor' => $vatTotal->toPrimitive(),
                'shipping_minor' => $shipping->toPrimitive(),
                'total_minor' => $total->toPrimitive(),
                'status' => 'paid',
                'notes' => null,
                'placed_at' => $placedAt,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ];
        }

        DB::table('receipts')->insert($receipts);
        foreach (array_chunk($lines, 1_000) as $slice) {
            DB::table('receipt_lines')->insert($slice);
        }

        $this->command->info('  seeded '.count($receipts).' receipts / '.count($lines).' lines');
    }
}
