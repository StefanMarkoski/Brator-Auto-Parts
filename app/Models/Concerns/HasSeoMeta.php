<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * SEO fields live in one polymorphic table rather than as columns on products,
 * categories, posts and pages — four tables and four migrations every time SEO
 * wants another field, and four more columns on the products hot row.
 */
trait HasSeoMeta
{
    /** @return MorphOne<SeoMeta, $this> */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}
