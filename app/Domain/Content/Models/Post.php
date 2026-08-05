<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Models\Concerns\HasSeoMeta;
use App\Models\User;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Blog pages are out of MVP scope, but this table is not: it feeds the theme's
 * "Articles & Reviews" homepage strip and the product page's "Guide & Blog" block,
 * both of which ship as designed markup.
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasSeoMeta;
    use HasUlids;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'cover_path', 'author_id',
        'post_category_id', 'is_published', 'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /** @param  Builder<Post>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('published_at');
    }
}
