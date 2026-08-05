<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\StockMovementReason;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'delta', 'reason', 'reference_type', 'reference_id', 'note', 'created_by',
    ];

    protected $casts = [
        'reason' => StockMovementReason::class,
        'delta' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
