<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How a re-import updates the right product instead of creating a duplicate. */
class ProductExternalRef extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'source_id', 'external_id'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
