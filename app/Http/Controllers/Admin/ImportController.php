<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\CatalogImport\Actions\PurgeImportSourceAction;
use App\Domain\CatalogImport\Actions\RevertImportAction;
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
        private RevertImportAction $revert,
        private PurgeImportSourceAction $purge,
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
            // Why the undo button is unavailable, or null when it is available. Worked out here
            // so the button can explain itself rather than being mysteriously greyed out.
            'cannotRevert' => $this->revert->reasonItCannotRevert($model),
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

    /**
     * Undo an applied import — for testing a feed over and over without clearing it by hand.
     *
     * Deletes only what the run CREATED. A row that updated an existing product is left alone:
     * the importer does not keep the values it overwrote, so there is nothing to restore, and
     * deleting the product would remove something that predates the feed.
     */
    public function destroy(string $run): RedirectResponse
    {
        $model = ImportRun::query()->findOrFail($run);

        try {
            $result = $this->revert->execute($model);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.imports.show', $model->id)->with('error', $e->getMessage());
        }

        $message = $result['deleted'] === 1
            ? '1 imported product was removed.'
            : "{$result['deleted']} imported products were removed.";

        if ($result['kept'] > 0) {
            // Named, because "undo" that leaves rows behind has to say which and why.
            $message .= sprintf(' %d row%s updated a product that already existed and %s left '
                .'untouched — the feed does not record what it overwrote, so there is nothing to '
                .'put back.', $result['kept'], $result['kept'] === 1 ? '' : 's',
                $result['kept'] === 1 ? 'was' : 'were');
        }

        if ($result['receiptsUnlinked'] > 0) {
            $message .= sprintf(' %d receipt line%s no longer links to a product; %s own record '
                .'of the name, price and VAT is untouched.', $result['receiptsUnlinked'],
                $result['receiptsUnlinked'] === 1 ? '' : 's',
                $result['receiptsUnlinked'] === 1 ? 'its' : 'their');
        }

        if ($result['brandsRemoved'] !== []) {
            $message .= ' Empty brand'.(count($result['brandsRemoved']) === 1 ? '' : 's')
                .' removed too: '.implode(', ', $result['brandsRemoved']).'.';
        }

        return redirect()->route('admin.imports.show', $model->id)->with('status', $message);
    }

    /**
     * Erase a supplier: every part it created, its whole run history, and the supplier row.
     *
     * Separate from undoing a run, and both are wanted. Undo is for testing a feed — one run,
     * most recent first, a marker left behind. This is for "that supplier was a trial, get rid of
     * it": every run at once, in any order, nothing left for the next upload to trip over.
     */
    public function purgeSource(string $source): RedirectResponse
    {
        $model = ImportSource::query()->findOrFail($source);
        $name = $model->name;

        $result = $this->purge->execute($model);

        $message = sprintf('%s is gone: %d part%s removed and %d run%s deleted.',
            $name,
            $result['deleted'], $result['deleted'] === 1 ? '' : 's',
            $result['runs'], $result['runs'] === 1 ? '' : 's');

        if ($result['kept'] > 0) {
            // Said out loud: a purge that leaves products behind has to name how many and why.
            $message .= sprintf(' %d product%s that the feed only UPDATED %s kept — %s existed '
                .'before this supplier, and the feed does not record what it overwrote.',
                $result['kept'], $result['kept'] === 1 ? '' : 's',
                $result['kept'] === 1 ? 'was' : 'were',
                $result['kept'] === 1 ? 'it' : 'they');
        }

        if ($result['receiptsUnlinked'] > 0) {
            $message .= sprintf(' %d receipt line%s no longer links to a product; %s own record of '
                .'the name, price and VAT is untouched.', $result['receiptsUnlinked'],
                $result['receiptsUnlinked'] === 1 ? '' : 's',
                $result['receiptsUnlinked'] === 1 ? 'its' : 'their');
        }

        if ($result['basketLines'] > 0) {
            $message .= sprintf(' %d open basket line%s cleared.',
                $result['basketLines'], $result['basketLines'] === 1 ? '' : 's');
        }

        if ($result['brandsRemoved'] !== []) {
            $message .= ' Empty brand'.(count($result['brandsRemoved']) === 1 ? '' : 's')
                .' removed too: '.implode(', ', $result['brandsRemoved']).'.';
        }

        return redirect()->route('admin.imports.index')->with('status', $message);
    }
}
