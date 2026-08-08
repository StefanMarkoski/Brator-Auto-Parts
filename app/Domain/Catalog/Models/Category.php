<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Queries\Public\GetNavigationQuery;
use App\Models\Concerns\HasSeoMeta;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasSeoMeta;
    use HasUlids;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image_path',
        'path', 'depth', 'position', 'is_active', 'products_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'depth' => 'integer',
        'position' => 'integer',
        'products_count' => 'integer',
    ];

    /**
     * Any change to a category drops the cached navigation tree.
     *
     * GetNavigationQuery caches that tree for an hour and it is injected into every page by a
     * view composer, so a rename used to leave the mega menu, the mobile menu and the footer
     * all linking the OLD slug — and the department page resolves by slug, so the shop's own
     * primary navigation 404'd for up to an hour with no control in the panel to fix it.
     *
     * Hooked on the MODEL rather than called from SaveCategoryAction/DeleteCategoryAction on
     * purpose. Those are the two write paths today; the reason this bug existed at all is that
     * `forget()` has to be remembered, and the icon migration was the only caller that did.
     * Here it cannot be forgotten by whatever writes a category next.
     */
    protected static function booted(): void
    {
        static::saved(static fn () => GetNavigationQuery::forget());
        static::deleted(static fn () => GetNavigationQuery::forget());
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')
            ->withPivot(['is_primary', 'position']);
    }

    /** @return BelongsToMany<Attribute, $this> */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes')
            ->withPivot('position')
            ->orderBy('category_attributes.position');
    }

    /**
     * Everything at or beneath this category, resolved by the materialized path in
     * one indexed query rather than a recursive walk.
     *
     * @param  Builder<Category>  $query
     */
    public function scopeInSubtree(Builder $query, self $root): void
    {
        $query->where('path', 'like', $root->path.'%');
    }

    /** Path is "/parent-slug/own-slug/" — always leading and trailing slash. */
    public function buildPath(): string
    {
        return ($this->parent?->path ?? '/').$this->slug.'/';
    }
}
