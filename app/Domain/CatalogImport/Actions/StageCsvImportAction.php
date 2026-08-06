<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Actions;

use App\Domain\CatalogImport\DTOs\ImportRow;
use App\Domain\CatalogImport\Enums\ImportRunStatus;
use App\Domain\CatalogImport\Enums\StagingRowOutcome;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Domain\CatalogImport\Models\ImportSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads a supplier CSV into import_staging_rows. Writes NOTHING to the catalogue.
 *
 * Staging first is the whole point of the design. It means the file can be checked, counted
 * and shown to whoever uploaded it BEFORE a single product moves — and it means a run that
 * fails halfway leaves a record of exactly which rows were applied and which were not.
 *
 * The alternative, parsing and writing in one pass, gives you a half-imported catalogue and
 * no way to tell where it stopped.
 */
final class StageCsvImportAction
{
    private const MAX_ROWS = 5_000;

    public function execute(ImportSource $source, string $absolutePath): ImportRun
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Cannot read the uploaded file at {$absolutePath}.");
        }

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Could not open the uploaded file.');
        }

        try {
            $header = $this->readHeader($handle);
            $rows = $this->readRows($handle, $header);
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new RuntimeException('The file has a header but no rows.');
        }

        return DB::transaction(function () use ($source, $rows): ImportRun {
            $run = ImportRun::create([
                'source_id' => $source->id,
                'status' => ImportRunStatus::Queued,
                'rows_total' => count($rows),
            ]);

            // Chunked: a 5,000-row feed is one insert per 500 rather than 5,000 inserts.
            foreach (array_chunk($rows, 500) as $chunk) {
                $run->stagingRows()->insert(array_map(fn (ImportRow $row): array => [
                    'import_run_id' => $run->id,
                    'external_id' => $row->sku,
                    'payload' => json_encode($row->toArray(), JSON_THROW_ON_ERROR),
                    'outcome' => StagingRowOutcome::Pending->value,
                ], $chunk));
            }

            Log::info('catalog_import.stage_csv.success', [
                'source_id' => $source->id,
                'run_id' => $run->id,
                'rows' => count($rows),
            ]);

            return $run;
        });
    }

    /**
     * @param  resource  $handle
     * @return array<int, string>
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle);

        if ($header === false || $header === [null]) {
            throw new RuntimeException('The file is empty.');
        }

        // A UTF-8 BOM survives on the first column name and turns "sku" into "\u{FEFF}sku",
        // which then matches nothing. Excel writes one by default.
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);

        $header = array_map(
            static fn ($name): string => str_replace(' ', '_', strtolower(trim((string) $name))),
            $header,
        );

        $missing = array_diff(ImportRow::REQUIRED, $header);

        if ($missing !== []) {
            throw new RuntimeException(
                'The file is missing required column(s): '.implode(', ', $missing)
                .'. Expected a header row with: '.implode(', ', ImportRow::COLUMNS).'.'
            );
        }

        return $header;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $header
     * @return list<ImportRow>
     */
    private function readRows($handle, array $header): array
    {
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            // A trailing newline produces [null]; a blank line in the middle of a file is
            // not an error either.
            if ($values === [null] || $this->isBlank($values)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException(
                    'The file has more than '.number_format(self::MAX_ROWS).' rows. Split it and '
                    .'import in batches — this runs in the request, not a queue.'
                );
            }

            $mapped = ['line' => $line];

            foreach ($header as $index => $column) {
                if (in_array($column, ImportRow::COLUMNS, true)) {
                    $mapped[$column] = $values[$index] ?? null;
                }
            }

            $rows[] = ImportRow::fromArray($mapped, $line);
        }

        return $rows;
    }

    /** @param array<int, mixed> $values */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
