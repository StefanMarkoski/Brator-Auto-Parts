<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Fitment\Enums\FuelType;
use Database\Factories\VehicleVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The level a shopper actually picks: "2.0 TDI 2015-2019", not just "Passat".
 * Fills the theme's Sub Model + Engine dropdowns.
 */
class VehicleVariant extends Model
{
    /** @use HasFactory<VehicleVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'model_id', 'name', 'year_from', 'year_to', 'engine_code',
        'fuel_type', 'power_kw', 'engine_cc', 'body_type', 'is_active',
    ];

    protected $casts = [
        'fuel_type' => FuelType::class,
        'year_from' => 'integer',
        'year_to' => 'integer',
        'power_kw' => 'integer',
        'engine_cc' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<VehicleModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_vehicle_fitments')
            ->withPivot(['year_from', 'year_to', 'note']);
    }

    /** "2015 - 2019" or "2015 - present". */
    public function yearRange(): string
    {
        return $this->year_from.' - '.($this->year_to ?? 'present');
    }

    public function fullName(): string
    {
        return trim("{$this->name} {$this->engine_code}").' ('.$this->yearRange().')';
    }
}
