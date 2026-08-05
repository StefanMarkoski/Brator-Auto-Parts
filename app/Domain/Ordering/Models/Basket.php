<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Fitment\Models\VehicleVariant;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Basket extends Model
{
    use HasUlids;

    protected $fillable = ['session_token', 'vehicle_variant_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    /** @return HasMany<BasketLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BasketLine::class);
    }

    /** @return BelongsTo<VehicleVariant, $this> */
    public function vehicleVariant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class);
    }

    /** Net of VAT, summed from the lines. Never trusted from a form. */
    public function subtotal(): Money
    {
        return $this->lines->reduce(
            fn (Money $carry, BasketLine $line): Money => $carry->add($line->lineTotal()),
            Money::zero()
        );
    }
}
