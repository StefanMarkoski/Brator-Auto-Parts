<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\CatalogImport\Actions\RunImportAction;
use App\Domain\CatalogImport\Actions\StageCsvImportAction;
use App\Domain\CatalogImport\DTOs\ImportRow;
use App\Domain\CatalogImport\Enums\ImportRunStatus;
use App\Domain\CatalogImport\Enums\ImportSourceType;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Domain\CatalogImport\Models\ImportSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

/**
 * The import runner.
 *
 * Three steps, deliberately separate: UPLOAD stages the file and writes nothing; PREVIEW says
 * what would happen; APPLY does it. Anything that can create or reprice a thousand products
 * in one click should make you look at the numbers first.
 */
final class ImportController
{
    public function __construct(
        private StageCsvImportAction $stage,
        private RunImportAction $runner,
    ) {}

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_name' => ['required', 'string', 'max:120'],
            // `file` with a mimes list rather than `mimetypes:text/csv`: browsers disagree
            // wildly about what MIME a .csv has, and Excel-saved files often arrive as
            // application/vnd.ms-excel.
            'feed' => ['required', 'file', 'mimes:csv,txt', 'max:8192'],
        ]);

        $source = ImportSource::query()->firstOrCreate(
            ['name' => $validated['source_name']],
            ['type' => ImportSourceType::Csv, 'is_active' => true],
        );

        try {
            $run = $this->stage->execute($source, $request->file('feed')->getRealPath());
        } catch (RuntimeException $e) {
            return redirect()->route('admin.imports.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.imports.show', $run->id)
            ->with('status', "{$run->rows_total} rows read from {$source->name}. Nothing has been "
                .'changed yet — check the preview below.');
    }

    /** The preview: a dry run, recomputed on each visit so it reflects the catalogue now. */
    public function show(string $run): View
    {
        $model = ImportRun::query()->with('source')->findOrFail($run);

        $preview = $model->status === ImportRunStatus::Queued
            ? $this->runner->execute($model, dryRun: true)
            : null;

        return view('admin.pages.import-run', [
            'run' => $model,
            'preview' => $preview,
            'rows' => $model->stagingRows()->orderBy('id')->limit(50)->get(),
            'columns' => ImportRow::COLUMNS,
        ]);
    }

    public function apply(Request $request, string $run): RedirectResponse
    {
        $model = ImportRun::query()->findOrFail($run);

        if ($model->status !== ImportRunStatus::Queued) {
            // Applying twice would re-run every row. Updates are idempotent, but the second
            // pass would also re-stamp stock and log a second set of movements.
            return redirect()
                ->route('admin.imports.show', $model->id)
                ->with('error', 'This run has already been applied.');
        }

        try {
            $result = $this->runner->execute($model, dryRun: false, actorId: $request->user()?->id);
        } catch (RuntimeException $e) {
            $model->update(['status' => ImportRunStatus::Failed, 'finished_at' => now()]);

            Log::error('catalog_import.run_import.failed', [
                'run_id' => $model->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.imports.show', $model->id)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.imports.show', $model->id)
            ->with('status', sprintf(
                '%d created, %d updated, %d skipped, %d failed. New products arrive unpublished '
                .'— publish them when you have had a look.',
                $result['created'], $result['updated'], $result['skipped'], $result['failed'],
            ));
    }
}
