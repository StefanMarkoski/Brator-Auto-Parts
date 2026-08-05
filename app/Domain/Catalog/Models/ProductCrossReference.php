<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CrossReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCrossReference extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'number', 'number_normalized', 'type', 'brand_hint'];

    protected $casts = ['type' => CrossReferenceType::class];

    /**
     * Strip everything a human might type differently. This is why pasting
     * "A 000 989 82 01" off an old part finds "a000989820-1".
     */
    public static function normalise(string $number): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $number));
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
