<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The largest table in the database, and the only one with a composite primary key
 * — (vehicle_variant_id, product_id), vehicle first, so that "parts for my car" is a
 * contiguous range scan over the clustered index.
 *
 * Treat it as a link table, never an aggregate: it has no id, so there is nothing to
 * find() and rows are written with insert()/upsert() rather than save(). Anything you
 * would want to look up by key gets a real key instead.
 */
class ProductVehicleFitment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'product_vehicle_fitments';

    protected $fillable = ['vehicle_variant_id', 'product_id', 'year_from', 'year_to', 'note'];

    protected $casts = [
        'year_from' => 'integer',
        'year_to' => 'integer',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<VehicleVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }
}
