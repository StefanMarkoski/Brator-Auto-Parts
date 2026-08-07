<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Actions;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\CatalogImport\Enums\StagingRowOutcome;
use App\Domain\CatalogImport\Models\ImportSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Erase a supplier from the shop: every part it ever created, its whole run history, and the
 * supplier row itself.
 *
 * DIFFERENT FROM UNDOING AN IMPORT, and both are needed. Undo takes back one run, refuses if a
 * later run has been applied, and leaves a marker behind — it is for testing a feed. This is for
 * "that supplier was a trial, get rid of it": every run at once, in any order, no marker, nothing
 * left to trip over the next time the same file is uploaded.
 *
 * WHAT IT WILL NOT DELETE is a product the feed only UPDATED. Those existed before the supplier
 * ever sent a file — the importer does not snapshot what it overwrote, so there is nothing to put
 * back, and removing them would destroy a part of the catalogue that was never theirs. They are
 * counted and reported instead.
 *
 * The delete is HARD, unlike the admin's own product delete which is soft. A soft delete leaves
 * the SKU occupied, and an occupied SKU is exactly what made re-importing the same file fail.
 */
final class PurgeImportSourceAction
{
    /**
     * @return array{deleted: int, kept: int, runs: int, receiptsUnlinked: int, basketLines: int, brandsRemoved: list<string>}
     */
    public function execute(ImportSource $source): array
    {
        return DB::transaction(function () use ($source): array {
            $runIds = $source->runs()->pluck('id');

            $createdIds = $this->createdBy($runIds);
            $kept = count($this->keptBy($runIds, $createdIds));

            $receiptsUnlinked = 0;
            $basketLines = 0;
            $deleted = 0;
            $brandsRemoved = [];

            if ($createdIds !== []) {
                /*
                 | Receipt lines keep their own copy of the name, SKU, brand, price and VAT — that
                 | is what makes a receipt sealed — and product_id is ON DELETE SET NULL. So a sold
                 | part can be removed without damaging the receipt: the money still adds up and
                 | the line still says what was bought. Only the link to a product page is lost,
                 | which is counted and reported rather than hidden.
                */
                $receiptsUnlinked = DB::table('receipt_lines')->whereIn('product_id', $createdIds)->count();

                // basket_lines is ON DELETE RESTRICT, so a part sitting in an open basket would
                // abort the whole purge with a foreign key error and no explanation.
                $basketLines = DB::table('basket_lines')->whereIn('product_id', $createdIds)->delete();

                // Read BEFORE the delete: which brands might be left with nothing in them.
                $brandIds = Product::withTrashed()->whereIn('id', $createdIds)
                    ->whereNotNull('brand_id')->distinct()->pluck('brand_id')->all();

                // withTrashed, because staff may already have deleted some by hand — which is the
                // state that broke the next import, and the state this has to be able to clear.
                $deleted = Product::withTrashed()->whereIn('id', $createdIds)->forceDelete();

                foreach (Brand::query()->whereIn('id', $brandIds)->get() as $brand) {
                    // Only if genuinely empty, so a brand that existed before the feed, or that
                    // other products use, is never touched.
                    if (! Product::withTrashed()->where('brand_id', $brand->id)->exists()) {
                        $brandsRemoved[] = $brand->name;
                        $brand->delete();
                    }
                }
            }

            $runs = $runIds->count();

            /*
             | The source goes too, and that is the "from everywhere" part. Staging rows cascade
             | with their run, runs cascade with the source. Uploading the same supplier again
             | recreates it by name, so nothing is lost by removing it — and leaving a supplier
             | behind with no runs and no parts would just be a row nobody can explain.
            */
            $source->delete();

            Log::info('catalog_import.purge_source.success', [
                'source' => $source->name,
                'runs' => $runs,
                'deleted' => $deleted,
                'kept_updates' => $kept,
                'receipt_lines_unlinked' => $receiptsUnlinked,
                'basket_lines_removed' => $basketLines,
                'brands_removed' => $brandsRemoved,
            ]);

            return [
                'deleted' => $deleted,
                'kept' => $kept,
                'runs' => $runs,
                'receiptsUnlinked' => $receiptsUnlinked,
                'basketLines' => $basketLines,
                'brandsRemoved' => $brandsRemoved,
            ];
        });
    }

    /**
     * What pressing the button will actually do, for the confirmation that asks first.
     *
     * @return array{products: int, kept: int, runs: int, sold: int}
     */
    public function preview(ImportSource $source): array
    {
        $runIds = $source->runs()->pluck('id');
        $createdIds = $this->createdBy($runIds);

        return [
            'products' => count($createdIds),
            'kept' => count($this->keptBy($runIds, $createdIds)),
            'runs' => $runIds->count(),
            'sold' => $createdIds === [] ? 0 : DB::table('receipt_lines')
                ->whereIn('product_id', $createdIds)->count(),
        ];
    }

    /**
     * The products this source created AND THAT STILL EXIST.
     *
     * Filtered against the products table on purpose: a source that has been undone once already
     * has staging rows pointing at ids that were hard-deleted then, and counting those told staff
     * "20 parts will be deleted" when the shop held ten.
     *
     * withTrashed, because a part staff already deleted by hand still occupies its SKU — and an
     * occupied SKU is exactly what makes the next import of the same file fail.
     *
     * @param  Collection<int, string>  $runIds
     * @return list<string>
     */
    private function createdBy(Collection $runIds): array
    {
        $ids = DB::table('import_staging_rows')
            ->whereIn('import_run_id', $runIds)
            ->where('outcome', StagingRowOutcome::Created->value)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return Product::withTrashed()->whereIn('id', $ids)->pluck('id')->all();
    }

    /**
     * The products this source only UPDATED, which the purge leaves alone.
     *
     * Minus the created ones, because a feed that created a part in one run and updated it in the
     * next would otherwise be reported as both deleted and kept in the same sentence.
     *
     * @param  Collection<int, string>  $runIds
     * @param  list<string>  $createdIds
     * @return list<string>
     */
    private function keptBy(Collection $runIds, array $createdIds): array
    {
        $ids = DB::table('import_staging_rows')
            ->whereIn('import_run_id', $runIds)
            ->where('outcome', StagingRowOutcome::Updated->value)
            ->whereNotNull('product_id')
            ->whereNotIn('product_id', $createdIds === [] ? [''] : $createdIds)
            ->distinct()
            ->pluck('product_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return Product::withTrashed()->whereIn('id', $ids)->pluck('id')->all();
    }
}
