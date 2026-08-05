<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\AttributeOptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeOption extends Model
{
    /** @use HasFactory<AttributeOptionFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = ['attribute_id', 'value', 'swatch_hex', 'position'];

    protected $casts = ['position' => 'integer'];

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
