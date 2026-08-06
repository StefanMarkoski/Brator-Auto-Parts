<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Models;

use App\Domain\CatalogImport\Enums\ImportRunStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'source_id', 'status', 'started_at', 'finished_at', 'reverted_at', 'rows_total',
        'rows_created', 'rows_updated', 'rows_skipped', 'rows_failed', 'log_path',
    ];

    protected $casts = [
        'status' => ImportRunStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'reverted_at' => 'datetime',
        'rows_total' => 'integer',
        'rows_created' => 'integer',
        'rows_updated' => 'integer',
        'rows_skipped' => 'integer',
        'rows_failed' => 'integer',
    ];

    /** @return BelongsTo<ImportSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }

    /** @return HasMany<ImportStagingRow, $this> */
    public function stagingRows(): HasMany
    {
        return $this->hasMany(ImportStagingRow::class, 'import_run_id');
    }
}
