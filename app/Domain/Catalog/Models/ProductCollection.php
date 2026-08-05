<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductCollection extends Model
{
    use HasUlids;

    protected $fillable = ['slug', 'name', 'type', 'rule', 'limit', 'is_active'];

    protected $casts = [
        'rule' => 'array',
        'limit' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_collection_items')
            ->withPivot('position')
            ->orderBy('product_collection_items.position');
    }

    public function isAutomatic(): bool
    {
        return $this->type === 'automatic';
    }
}
