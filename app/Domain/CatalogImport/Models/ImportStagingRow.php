<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Models;

use App\Domain\CatalogImport\Enums\StagingRowOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A scratchpad, not history — safe to truncate between runs. */
class ImportStagingRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_run_id', 'external_id', 'payload', 'outcome', 'error', 'product_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'outcome' => StagingRowOutcome::class,
    ];

    /** @return BelongsTo<ImportRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
