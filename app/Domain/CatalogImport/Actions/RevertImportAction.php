<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Actions;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\CatalogImport\Enums\ImportRunStatus;
use App\Domain\CatalogImport\Enums\StagingRowOutcome;
use App\Domain\CatalogImport\Models\ImportRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Undo an import: take back out what it put in.
 *
 * Built for testing a feed repeatedly. Without it, clearing 10 imported products between
 * attempts meant the same tinker command every time.
 *
 * WHAT IT UNDOES IS ONLY WHAT THE RUN CREATED. A row that UPDATED an existing product is left
 * completely alone, and that is not laziness — the importer does not snapshot the values it
 * overwrote, so there is nothing to put back. Deleting such a product would destroy something
 * that existed before the feed ever ran, which is the opposite of an undo.
 *
 * The delete is a HARD delete, unlike the admin's own product delete which is soft. A soft
 * delete would leave the SKU occupied, and the next import of the same file would fail on a
 * duplicate SKU — making the button useless for the one job it exists to do.
 */
final class RevertImportAction
{
    /**
     * @return array{deleted: int, kept: int, receiptsUnlinked: int, brandsRemoved: list<string>}
     *
     * @throws RuntimeException with a message written for the person pressing the button
     */
    public function execute(ImportRun $run): array
    {
        $this->assertRevertable($run);

        return DB::transaction(function () use ($run): array {
            $createdIds = $run->stagingRows()
                ->where('outcome', StagingRowOutcome::Created)
                ->whereNotNull('product_id')
                ->pluck('product_id')
                ->all();

            $updated = $run->stagingRows()->where('outcome', StagingRowOutcome::Updated)->count();

            if ($createdIds === []) {
                $run->update(['reverted_at' => now()]);

                return ['deleted' => 0, 'kept' => $updated, 'receiptsUnlinked' => 0, 'brandsRemoved' => []];
            }

            /*
             | Receipt lines keep their OWN copy of the name, SKU, brand, price, quantity and VAT
             | — that is what makes a receipt sealed — and product_id is nullable and ON DELETE
             | SET NULL. So a sold product can be removed without damaging the receipt: the money
             | still adds up and the line still says what was bought. The only thing lost is the
             | link through to a product page, which is counted and reported rather than hidden.
            */
            $receiptsUnlinked = DB::table('receipt_lines')->whereIn('product_id', $createdIds)->count();

            /*
             | basket_lines is ON DELETE RESTRICT, so a product sitting in somebody's open basket
             | would abort the whole delete with a foreign key error and no explanation. Cleared
             | first: an abandoned basket holding a part that is being withdrawn has to lose it
             | either way, and failing the revert instead would be a worse answer.
            */
            $basketLines = DB::table('basket_lines')->whereIn('product_id', $createdIds)->delete();

            // Which brands might be left empty afterwards — read BEFORE the delete.
            $brandIds = Product::withTrashed()->whereIn('id', $createdIds)
                ->whereNotNull('brand_id')->distinct()->pluck('brand_id')->all();

            // Everything else — categories, fitment, images, stock movements, cross references,
            // external refs, field overrides — is ON DELETE CASCADE, so it goes with the row.
            $deleted = Product::withTrashed()->whereIn('id', $createdIds)->forceDelete();

            /*
             | A brand the feed invented, now with nothing in it. Removed so that re-importing the
             | same file is a clean repeat rather than something that accumulates — but only if it
             | is genuinely empty, so a brand that existed before, or that other products use, is
             | never touched.
            */
            $brandsRemoved = [];

            foreach (Brand::query()->whereIn('id', $brandIds)->get() as $brand) {
                if (! Product::withTrashed()->where('brand_id', $brand->id)->exists()) {
                    $brandsRemoved[] = $brand->name;
                    $brand->delete();
                }
            }

            $run->update(['reverted_at' => now()]);

            Log::info('catalog_import.revert_import.success', [
                'run_id' => $run->id,
                'deleted' => $deleted,
                'kept_updates' => $updated,
                'receipt_lines_unlinked' => $receiptsUnlinked,
                'basket_lines_removed' => $basketLines,
                'brands_removed' => $brandsRemoved,
            ]);

            return [
                'deleted' => $deleted,
                'kept' => $updated,
                'receiptsUnlinked' => $receiptsUnlinked,
                'brandsRemoved' => $brandsRemoved,
            ];
        });
    }

    /** Whether this run can be undone at all, and why not if it cannot. */
    public function reasonItCannotRevert(ImportRun $run): ?string
    {
        if ($run->status !== ImportRunStatus::Completed) {
            return 'Only an applied import can be undone. This one was never applied, so there is '
                .'nothing in the shop to take out.';
        }

        if ($run->reverted_at !== null) {
            return 'This import was already undone on '.$run->reverted_at->format('j M Y, H:i').'.';
        }

        /*
         | Only the most recent applied import, and this is a real constraint rather than
         | caution. A later run may have updated the very products this one created — new price,
         | new stock — and deleting them would silently throw that away. Undo has to run
         | backwards or not at all.
        */
        $newer = ImportRun::query()
            ->where('status', ImportRunStatus::Completed)
            ->whereNull('reverted_at')
            ->whereKeyNot($run->id)
            /*
             | finished_at with the id as a tiebreaker, because two runs applied in the same
             | second have the SAME finished_at — and a strict > then reports no later run at all,
             | so both would look like "the most recent" and either could be undone first. Ids are
             | ULIDs, so they sort in creation order. Same trap as every other place in this
             | project where a timestamp was assumed unique.
            */
            ->where(fn ($q) => $q
                ->where('finished_at', '>', $run->finished_at)
                ->orWhere(fn ($tie) => $tie
                    ->where('finished_at', $run->finished_at)
                    ->where('id', '>', $run->id)))
            ->count();

        if ($newer > 0) {
            return 'A later import has been applied since this one. Undo the most recent import '
                .'first — a newer feed may have changed the very products this one created.';
        }

        return null;
    }

    private function assertRevertable(ImportRun $run): void
    {
        $reason = $this->reasonItCannotRevert($run);

        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
    }
}
