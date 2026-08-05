<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Models;

use Database\Factories\VehicleModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    /** @use HasFactory<VehicleModelFactory> */
    use HasFactory;

    protected $fillable = ['make_id', 'name', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return BelongsTo<VehicleMake, $this> */
    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'make_id');
    }

    /** @return HasMany<VehicleVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(VehicleVariant::class, 'model_id');
    }
}
