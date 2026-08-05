<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    use HasUlids;

    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type', 'seoable_id', 'meta_title', 'meta_description',
        'canonical_url', 'og_image_path', 'noindex',
    ];

    protected $casts = ['noindex' => 'boolean'];

    /** @return MorphTo<Model, $this> */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
