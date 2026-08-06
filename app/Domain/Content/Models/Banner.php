<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Actions\ImportHeroImageAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasUlids;

    protected $fillable = [
        'placement', 'title', 'subtitle', 'image_path', 'mobile_image_path',
        'source_url', 'image_width', 'image_height',
        'link_url', 'link_label', 'position', 'is_active', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'position' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Is this picture big enough for a full-bleed hero, or will it be upscaled?
     *
     * Seeded rows have no recorded dimensions, and those are the purchased theme's own slider
     * assets — known good — so an unmeasured image is treated as fine rather than nagged about.
     */
    public function isComfortableForHero(): bool
    {
        return $this->image_width === null
            || $this->image_width >= ImportHeroImageAction::COMFORTABLE_WIDTH;
    }

    /** "731 × 871", or null for rows that predate measuring. */
    public function dimensions(): ?string
    {
        return $this->image_width === null || $this->image_height === null
            ? null
            : $this->image_width.' × '.$this->image_height;
    }

    /**
     * Live now: active, and inside its scheduled window if it has one.
     *
     * @param  Builder<Banner>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('position');
    }
}
