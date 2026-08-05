<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductCondition;
use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Fitment\Models\VehicleVariant;
use App\Models\Concerns\HasSeoMeta;
use App\Support\Casts\MoneyCast;
use App\Support\ValueObjects\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasSeoMeta;
    use HasUlids;
    use SoftDeletes;

    /**
     * The columns a listing page needs to render a product card. Listing queries
     * select THIS and never `*` — pulling the description columns into a 24-card
     * page is the single easiest way to make the catalogue feel slow.
     *
     * @var list<string>
     */
    public const LISTING_COLUMNS = [
        'id', 'sku', 'name', 'slug', 'brand_id', 'price_minor', 'sale_price_minor',
        'stock_status', 'rating_avg', 'reviews_count', 'condition',
    ];

    protected $fillable = [
        'sku', 'name', 'slug', 'brand_id', 'price_minor', 'sale_price_minor',
        'stock_quantity', 'stock_status', 'condition', 'weight_grams',
        'rating_avg', 'reviews_count', 'is_active', 'published_at',
        'short_description', 'description',
    ];

    protected $casts = [
        'price_minor' => MoneyCast::class,
        'sale_price_minor' => MoneyCast::class,
        'stock_status' => StockStatus::class,
        'condition' => ProductCondition::class,
        'stock_quantity' => 'integer',
        'rating_avg' => 'float',
        'reviews_count' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
            ->withPivot(['is_primary', 'position']);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** @return HasMany<ProductCrossReference, $this> */
    public function crossReferences(): HasMany
    {
        return $this->hasMany(ProductCrossReference::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<ProductReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /** @return BelongsToMany<VehicleVariant, $this> */
    public function vehicleVariants(): BelongsToMany
    {
        return $this->belongsToMany(VehicleVariant::class, 'product_vehicle_fitments')
            ->withPivot(['year_from', 'year_to', 'note']);
    }

    /** The price a shopper actually pays, net of VAT. */
    protected function effectivePrice(): CastAttribute
    {
        return CastAttribute::get(
            fn (): Money => $this->sale_price_minor ?? $this->price_minor
        );
    }

    public function isOnSale(): bool
    {
        return $this->sale_price_minor !== null
            && $this->sale_price_minor->minor < $this->price_minor->minor;
    }

    /** The category that owns the canonical URL and breadcrumb. */
    public function primaryCategory(): ?Category
    {
        return $this->categories->firstWhere('pivot.is_primary', true);
    }

    /** @param  Builder<Product>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereNotNull('published_at');
    }
}
