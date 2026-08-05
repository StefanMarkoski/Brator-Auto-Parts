<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Exceptions\ReceiptIsSealedException;
use App\Support\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The *_snapshot columns are the point of a receipt, not redundancy: a receipt
 * records what was bought at a moment in time. Read live product data here and
 * renaming a part silently rewrites history.
 */
class ReceiptLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'receipt_id', 'product_id', 'product_name_snapshot', 'product_sku_snapshot',
        'brand_name_snapshot', 'unit_price_minor', 'quantity', 'line_total_minor',
        'vat_rate', 'vat_minor',
    ];

    protected $casts = [
        'unit_price_minor' => MoneyCast::class,
        'line_total_minor' => MoneyCast::class,
        'vat_minor' => MoneyCast::class,
        'vat_rate' => 'float',
        'quantity' => 'integer',
    ];

    /**
     * A receipt line is part of the financial record. It may be created, never changed
     * or removed — the same reasoning as the receipt itself.
     */
    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            throw new ReceiptIsSealedException(
                'Receipt lines cannot be changed once written. They are the record of '
                .'what was actually sold.'
            );
        });

        static::deleting(function (self $line): void {
            throw new ReceiptIsSealedException('Receipt lines cannot be deleted.');
        });
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** Nullable: the product may be gone, the receipt still reads correctly. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
