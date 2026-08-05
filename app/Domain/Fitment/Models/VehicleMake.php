<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Models;

use Database\Factories\VehicleMakeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Integer key: reference data, joined constantly, never linked to publicly. */
class VehicleMake extends Model
{
    /** @use HasFactory<VehicleMakeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo_path', 'position', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'position' => 'integer'];

    /** @return HasMany<VehicleModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class, 'make_id');
    }
}
