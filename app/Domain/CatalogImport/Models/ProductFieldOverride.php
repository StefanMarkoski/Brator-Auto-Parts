<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The whole answer to "import plus manual overrides": a staff member edits a field by
 * hand, we record that they own it, and the importer refuses to touch anything listed
 * here. One rule, one place — rather than an is_manual boolean beside every column,
 * which spreads the same rule across dozens of columns and gets forgotten on the next.
 */
class ProductFieldOverride extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'field_name', 'overridden_by', 'overridden_at'];

    protected $casts = ['overridden_at' => 'datetime'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Field names an importer may never write for this product.
     *
     * @return list<string>
     */
    public static function lockedFieldsFor(string $productId): array
    {
        return self::query()->where('product_id', $productId)->pluck('field_name')->all();
    }
}
