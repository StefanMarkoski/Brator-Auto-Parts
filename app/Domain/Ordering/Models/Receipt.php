<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Support\Casts\MoneyCast;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'receipt_number', 'customer_id', 'customer_name', 'customer_email',
        'customer_phone', 'shipping_address', 'billing_address',
        'subtotal_minor', 'vat_minor', 'shipping_minor', 'total_minor',
        'status', 'notes', 'placed_at',
    ];

    protected $casts = [
        'subtotal_minor' => MoneyCast::class,
        'vat_minor' => MoneyCast::class,
        'shipping_minor' => MoneyCast::class,
        'total_minor' => MoneyCast::class,
        'status' => ReceiptStatus::class,
        'placed_at' => 'datetime',
    ];

    /** @return HasMany<ReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class);
    }
}
