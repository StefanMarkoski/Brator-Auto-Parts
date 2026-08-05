<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message', 'ip_address', 'handled_at',
    ];

    protected $casts = ['handled_at' => 'datetime'];
}
