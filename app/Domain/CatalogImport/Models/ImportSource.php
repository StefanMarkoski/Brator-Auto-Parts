<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Models;

use App\Domain\CatalogImport\Enums\ImportSourceType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportSource extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'type', 'config', 'is_active', 'last_run_at'];

    protected $casts = [
        'type' => ImportSourceType::class,
        // Encrypted: supplier credentials live in here.
        'config' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /** @return HasMany<ImportRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ImportRun::class, 'source_id');
    }
}
