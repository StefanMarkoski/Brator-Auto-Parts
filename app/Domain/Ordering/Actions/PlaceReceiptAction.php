<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Enums\StockMovementReason;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\StockMovement;
use App\Domain\Ordering\DTOs\CheckoutDetails;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Events\ReceiptPlaced;
use App\Domain\Ordering\Exceptions\PriceChangedException;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use App\Domain\Ordering\Models\Receipt;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Turns a basket into a receipt. The payment is deliberately fake; the arithmetic and
 * the record are not.
 *
 * Touches basket, receipt, receipt lines, products and the stock ledger, so the whole
 * body is one transaction — a half-written order must not be able to exist.
 */
final class PlaceReceiptAction
{
    public function execute(Basket $basket, CheckoutDetails $details): Receipt
    {
        $basket->loadMissing(['lines.product.brand']);

        // A soft-deleted product nulls the relation. Drop those lines before totalling
        // rather than reading through them — the same fault family that used to 500 the
        // cart page.
        $orphaned = $basket->lines->filter(fn ($line): bool => $line->product === null);

        if ($orphaned->isNotEmpty()) {
            BasketLine::query()->whereIn('id', $orphaned->pluck('id'))->delete();
            $basket->unsetRelation('lines')->load('lines.product.brand');
        }

        if ($basket->lines->isEmpty()) {
            throw new RuntimeException('Cannot place a receipt for an empty basket.');
        }

        $vatRate = (float) config('shop.vat_rate');

        $receipt = DB::transaction(function () use ($basket, $details, $vatRate): Receipt {
            $subtotal = Money::zero();
            $vatTotal = Money::zero();
            $lines = [];

            foreach ($basket->lines as $line) {
                /** @var Product $product */
                $product = $line->product;

                if ($product === null || ! $product->isPurchasable()) {
                    throw new RuntimeException(
                        ($product?->name ?? 'One of the parts in your cart')
                        .' '.($product?->unpurchasableReason() ?? 'is no longer available')
                        .'. Please remove it and try again.'
                    );
                }

                if ($line->quantity > (int) $product->stock_quantity) {
                    throw new RuntimeException(
                        "Only {$product->stock_quantity} of {$product->name} remain. "
                        .'Please lower the quantity and try again.'
                    );
                }

                // The price the shopper agreed to is the one on the basket line. If the
                // live price has moved since they added it, STOP and tell them — do not
                // silently substitute.
                //
                // The first version of this re-read the live price and called that
                // "re-validation". It is not: re-reading is not re-validating. The cart
                // showed 1.000 and the receipt charged 3.000, which is charging someone
                // a price they never saw and never agreed to.
                $live = $product->sale_price_minor ?? $product->price_minor;

                if (! $live->equals($line->unit_price_minor)) {
                    throw new PriceChangedException(
                        "The price of {$product->name} changed from "
                        ."{$line->unit_price_minor->format()} to {$live->format()} while it was "
                        .'in your cart. Please review your cart and place the order again.'
                    );
                }

                $unit = $line->unit_price_minor;
                $lineTotal = $unit->timesQuantity($line->quantity);
                $lineVat = $lineTotal->vatAt($vatRate);

                $subtotal = $subtotal->add($lineTotal);
                $vatTotal = $vatTotal->add($lineVat);

                $lines[] = [
                    'product_id' => $product->id,
                    // Snapshots: this receipt must still read correctly after the
                    // product is renamed, repriced or deleted.
                    'product_name_snapshot' => $product->name,
                    'product_sku_snapshot' => $product->sku,
                    'brand_name_snapshot' => $product->brand?->name,
                    'unit_price_minor' => $unit->toPrimitive(),
                    'quantity' => $line->quantity,
                    'line_total_minor' => $lineTotal->toPrimitive(),
                    'vat_rate' => $vatRate,
                    'vat_minor' => $lineVat->toPrimitive(),
                ];
            }

            $shipping = Money::fromMinor($subtotal->minor >= 300_000 ? 0 : 19_000);

            $receipt = Receipt::create([
                'receipt_number' => $this->nextReceiptNumber(),
                'customer_name' => $details->customerName,
                'customer_email' => $details->customerEmail,
                'customer_phone' => $details->customerPhone,
                'shipping_address' => $details->shippingAddress,
                'subtotal_minor' => $subtotal->toPrimitive(),
                'vat_minor' => $vatTotal->toPrimitive(),
                'shipping_minor' => $shipping->toPrimitive(),
                'total_minor' => $subtotal->add($vatTotal)->add($shipping)->toPrimitive(),
                // The fake payment step. A real gateway would set the same state.
                'status' => ReceiptStatus::Paid,
                'notes' => $details->notes,
                'placed_at' => now(),
            ]);

            $receipt->lines()->createMany($lines);

            // The stock ledger, and the cached quantity in the same transaction so the
            // two cannot drift.
            foreach ($basket->lines as $line) {
                StockMovement::create([
                    'product_id' => $line->product_id,
                    'delta' => -$line->quantity,
                    'reason' => StockMovementReason::Sale,
                    'reference_type' => Receipt::class,
                    'reference_id' => $receipt->id,
                    'note' => 'Receipt '.$receipt->receipt_number,
                ]);

                Product::query()->whereKey($line->product_id)
                    ->decrement('stock_quantity', $line->quantity);
            }

            $basket->lines()->delete();

            return $receipt;
        });

        ReceiptPlaced::dispatch($receipt->id);

        Log::info('ordering.place_receipt.success', [
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'total_minor' => $receipt->total_minor->toPrimitive(),
            'lines' => $receipt->lines()->count(),
        ]);

        return $receipt;
    }

    /**
     * BR-YYYY-NNNNNN, sequential within the year. Locked inside the surrounding
     * transaction so two simultaneous checkouts cannot claim the same number.
     */
    private function nextReceiptNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "BR-{$year}-";

        $last = Receipt::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
