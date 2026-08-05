<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Enums\FilterWidget;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'code', 'label', 'type', 'unit', 'is_filterable', 'filter_widget', 'position',
    ];

    protected $casts = [
        'type' => AttributeType::class,
        'filter_widget' => FilterWidget::class,
        'is_filterable' => 'boolean',
        'position' => 'integer',
    ];

    /** @return HasMany<AttributeOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('position');
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attributes');
    }
}
