<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Exceptions\ReceiptIsSealedException;
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

    /**
     * Financial columns cannot be changed once the receipt exists.
     *
     * Snapshotting the values was only half the job: the reviewer rewrote a total to 1.
     * A snapshot is not a seal. Status and notes remain editable — staff legitimately
     * cancel orders and add remarks — but the money and the customer's details are
     * fixed, because a receipt is a record of what happened.
     *
     * @var list<string>
     */
    public const SEALED = [
        'receipt_number', 'subtotal_minor', 'vat_minor', 'shipping_minor',
        'total_minor', 'customer_name', 'customer_email', 'customer_phone',
        'shipping_address', 'billing_address', 'placed_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $receipt): void {
            $sealed = array_intersect(array_keys($receipt->getDirty()), self::SEALED);

            if ($sealed !== []) {
                throw new ReceiptIsSealedException(
                    "Receipt {$receipt->receipt_number} is a financial record. These "
                    .'fields cannot be changed: '.implode(', ', $sealed).'. Cancel the '
                    .'receipt and issue a new one instead.'
                );
            }
        });

        static::deleting(function (self $receipt): void {
            throw new ReceiptIsSealedException(
                "Receipt {$receipt->receipt_number} cannot be deleted. Set its status to "
                .'cancelled instead — deleting a receipt destroys the record that it '
                .'ever existed.'
            );
        });
    }

    /** @return HasMany<ReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class);
    }
}
