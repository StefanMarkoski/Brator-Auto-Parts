<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Models\Concerns\HasSeoMeta;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasSeoMeta;
    use HasUlids;

    protected $fillable = ['title', 'slug', 'body', 'is_published'];

    protected $casts = ['is_published' => 'boolean'];
}
