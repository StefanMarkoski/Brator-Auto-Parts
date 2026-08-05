<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Catalog\Models\Product;
use App\Support\Casts\MoneyCast;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasketLine extends Model
{
    use HasUlids;

    protected $fillable = ['basket_id', 'product_id', 'quantity', 'unit_price_minor'];

    protected $casts = [
        'unit_price_minor' => MoneyCast::class,
        'quantity' => 'integer',
    ];

    /** @return BelongsTo<Basket, $this> */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotal(): Money
    {
        return $this->unit_price_minor->timesQuantity($this->quantity);
    }
}
