<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Catalog\Models\ProductCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The dynamic homepage. Staff reorder, hide, retitle, and rebind which collection
 * feeds each section. They cannot invent a new section_type: each maps to a Blade
 * partial cut from the theme's existing markup, and a new type would need new markup
 * — which is the styling change that is forbidden.
 */
class HomepageSection extends Model
{
    use HasUlids;

    protected $fillable = [
        'section_type', 'heading', 'subheading', 'product_collection_id',
        'settings', 'position', 'is_visible',
    ];

    protected $casts = [
        'settings' => 'array',
        'position' => 'integer',
        'is_visible' => 'boolean',
    ];

    /** @return BelongsTo<ProductCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'product_collection_id');
    }

    /** @param  Builder<HomepageSection>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true)->orderBy('position');
    }

    /** The Blade partial this section renders through. */
    public function viewName(): string
    {
        return 'home.sections.'.str_replace('_', '-', $this->section_type);
    }
}
