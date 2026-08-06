<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Actions;

use App\Domain\Catalog\Actions\AdjustStockAction;
use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCrossReference;
use App\Domain\CatalogImport\DTOs\ImportRow;
use App\Domain\CatalogImport\Enums\ImportRunStatus;
use App\Domain\CatalogImport\Enums\StagingRowOutcome;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Domain\CatalogImport\Models\ProductFieldOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applies a staged run to the catalogue.
 *
 * THE RULE THIS EXISTS TO ENFORCE: an import never overwrites a field a human has edited.
 * That promise was made when product_field_overrides was built and enforced on the writing
 * side; until now there was no importer to test it against, which means it was a promise
 * nobody had ever kept or broken.
 *
 * Two decisions worth stating, because both could reasonably have gone the other way:
 *
 *   AN EXISTING SKU IS AN UPDATE, NOT A REPLACE. Only the columns the feed actually supplies
 *   are touched; a blank cell means "no opinion", not "clear it". A feed that omits
 *   short_description must not wipe the description somebody wrote.
 *
 *   A PRODUCT MISSING FROM THE FEED IS LEFT ALONE. Deactivating absentees is how suppliers
 *   usually signal a discontinued line, and it is also how a truncated file empties a shop.
 *   Not doing it silently is the safer default; doing it needs to be a deliberate,
 *   separately-confirmed action.
 *
 * Brands are CREATED when the feed names one we do not have — that is what makes a new
 * supplier appear in the brand filter on its own. Categories are NOT: a category is
 * navigation, and a supplier feed does not get to reshape the shop's departments.
 */
final class RunImportAction
{
    public function __construct(
        private CreateProductAction $createProduct,
        private AdjustStockAction $adjustStock,
    ) {}

    /**
     * @param  bool  $dryRun  when true, nothing is written and the counts are a preview
     * @return array{created: int, updated: int, skipped: int, failed: int, notes: list<string>}
     */
    public function execute(ImportRun $run, bool $dryRun = false, ?string $actorId = null): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $notes = [];

        if (! $dryRun) {
            $run->update(['status' => ImportRunStatus::Running, 'started_at' => now()]);
        }

        // Brand and category lookups are resolved once, not per row: a 5,000 row feed would
        // otherwise be 10,000 extra queries for values that barely change.
        $brands = Brand::query()->pluck('id', 'slug')->all();
        $categories = Category::query()->where('depth', 1)->pluck('id', 'slug')->all();
        $categoryNames = Category::query()->where('depth', 1)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $c): array => [Str::lower($c->name) => $c->id])
            ->all();

        foreach ($run->stagingRows()->orderBy('id')->cursor() as $staged) {
            $row = ImportRow::fromArray((array) $staged->payload);

            try {
                $outcome = $this->applyRow(
                    $row, $dryRun, $actorId, $brands, $categories, $categoryNames, $notes,
                );
            } catch (Throwable $e) {
                // One bad row must not abandon the other 4,999. The row is recorded as
                // failed with its reason and the run continues.
                $outcome = [StagingRowOutcome::Failed, null, $e->getMessage()];
            }

            [$result, $productId, $error] = $outcome;

            $counts[match ($result) {
                StagingRowOutcome::Created => 'created',
                StagingRowOutcome::Updated => 'updated',
                StagingRowOutcome::Failed => 'failed',
                default => 'skipped',
            }]++;

            if ($error !== null && count($notes) < 50) {
                $notes[] = "Row {$row->line} ({$row->sku}): {$error}";
            }

            if (! $dryRun) {
                $staged->update([
                    'outcome' => $result,
                    'product_id' => $productId,
                    'error' => $error,
                ]);
            }
        }

        if (! $dryRun) {
            $run->update([
                'status' => ImportRunStatus::Completed,
                'finished_at' => now(),
                'rows_created' => $counts['created'],
                'rows_updated' => $counts['updated'],
                'rows_skipped' => $counts['skipped'],
                'rows_failed' => $counts['failed'],
            ]);

            $run->source?->update(['last_run_at' => now()]);

            Log::info('catalog_import.run_import.success', [
                'run_id' => $run->id,
                'source_id' => $run->source_id,
                ...$counts,
            ]);
        }

        return [...$counts, 'notes' => $notes];
    }

    /**
     * @param  array<string, string>  $brands
     * @param  array<string, string>  $categories
     * @param  array<string, string>  $categoryNames
     * @param  list<string>  $notes
     * @return array{0: StagingRowOutcome, 1: ?string, 2: ?string}
     */
    private function applyRow(
        ImportRow $row,
        bool $dryRun,
        ?string $actorId,
        array &$brands,
        array $categories,
        array $categoryNames,
        array &$notes,
    ): array {
        $problem = $row->problem();

        if ($problem !== null) {
            return [StagingRowOutcome::Skipped, null, $problem];
        }

        $brandId = $this->resolveBrand($row->brand, $dryRun, $brands);
        $categoryId = $this->resolveCategory($row->category, $categories, $categoryNames);

        if ($row->category !== null && $categoryId === null) {
            // Refused rather than invented: a feed that could create departments would let a
            // supplier rearrange the shop's navigation.
            return [
                StagingRowOutcome::Skipped,
                null,
                "category '{$row->category}' does not exist. Create it first, or leave the column blank.",
            ];
        }

        $existing = Product::withTrashed()->where('sku', $row->sku)->first();

        return $existing === null
            ? $this->createFrom($row, $brandId, $categoryId, $dryRun, $actorId)
            : $this->updateFrom($row, $existing, $brandId, $categoryId, $dryRun, $actorId);
    }

    /** @return array{0: StagingRowOutcome, 1: ?string, 2: ?string} */
    private function createFrom(
        ImportRow $row,
        ?string $brandId,
        ?string $categoryId,
        bool $dryRun,
        ?string $actorId,
    ): array {
        if ($dryRun) {
            return [StagingRowOutcome::Created, null, null];
        }

        $product = $this->createProduct->execute(
            attributes: [
                'sku' => $row->sku,
                'name' => $row->name,
                'brand_id' => $brandId,
                'price_minor' => $row->priceMinor(),
                'sale_price_minor' => $row->salePriceMinor(),
                'stock_quantity' => (int) ($row->stock ?? 0),
                'condition' => Str::lower($row->condition ?? 'new'),
                'short_description' => $row->shortDescription,
                'is_active' => true,
                /*
                 | Imported products arrive UNPUBLISHED. A feed can add a thousand rows in one
                 | click; putting them straight in front of shoppers with no human glance
                 | means a supplier's typo is live on the shop before anyone reads it.
                 | Publishing stays a person's decision.
                */
                'published_at' => null,
            ],
            categoryIds: $categoryId === null ? [] : [$categoryId],
            actorId: $actorId,
        );

        $this->recordPartNumber($product, $row);

        return [StagingRowOutcome::Created, $product->id, null];
    }

    /** @return array{0: StagingRowOutcome, 1: ?string, 2: ?string} */
    private function updateFrom(
        ImportRow $row,
        Product $product,
        ?string $brandId,
        ?string $categoryId,
        bool $dryRun,
        ?string $actorId,
    ): array {
        // The fields a human owns on THIS product. The importer is forbidden from touching
        // any of them, which is the promise product_field_overrides exists to keep.
        $locked = ProductFieldOverride::lockedFieldsFor($product->id);

        /*
         | Only what the feed actually supplied. A blank cell is "no opinion", not "clear it":
         | a feed without a short_description column must not wipe copy somebody wrote.
        */
        $candidates = array_filter([
            'name' => $row->name,
            'brand_id' => $brandId,
            'price_minor' => $row->priceMinor(),
            'sale_price_minor' => $row->salePriceMinor(),
            'condition' => $row->condition === null ? null : Str::lower($row->condition),
            'short_description' => $row->shortDescription,
        ], fn ($value): bool => $value !== null);

        $changes = array_diff_key($candidates, array_flip($locked));
        $refused = array_intersect(array_keys($candidates), $locked);

        if ($dryRun) {
            return [
                StagingRowOutcome::Updated,
                $product->id,
                $refused === [] ? null : 'will not touch (yours): '.implode(', ', $refused),
            ];
        }

        if ($changes !== []) {
            $product->update($changes);
        }

        if ($categoryId !== null && ! $product->categories()->whereKey($categoryId)->exists()) {
            // Attached, never synced: syncing would drop categories a human added by hand.
            $product->categories()->attach($categoryId, ['is_primary' => false, 'position' => 1]);
        }

        // Stock is not a "field" in the override sense — it is a physical count, and it goes
        // through the ledger like every other stock change.
        if ($row->stock !== null && ! in_array('stock_quantity', $locked, true)) {
            $this->adjustStock->execute(
                productId: $product->id,
                countedQuantity: (int) $row->stock,
                actorId: $actorId,
                note: 'Supplier feed.',
            );
        }

        $this->recordPartNumber($product, $row);

        return [
            StagingRowOutcome::Updated,
            $product->id,
            $refused === [] ? null : 'left alone (yours): '.implode(', ', $refused),
        ];
    }

    /**
     * A new brand from the feed becomes a real brand, so a new supplier shows up in the brand
     * filter without anybody adding it by hand. Matched on the slug, so "XGate", "xgate" and
     * "X Gate " are the same maker rather than three.
     *
     * @param  array<string, string>  $brands
     */
    private function resolveBrand(?string $name, bool $dryRun, array &$brands): ?string
    {
        if ($name === null) {
            return null;
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        if (isset($brands[$slug])) {
            return $brands[$slug];
        }

        if ($dryRun) {
            return null;
        }

        $brand = Brand::create([
            'name' => $name,
            'slug' => $slug,
            // No logo: we have none, and the views render the name instead.
            'logo_path' => null,
            'is_active' => true,
            'position' => (int) Brand::query()->max('position') + 1,
        ]);

        $brands[$slug] = $brand->id;

        Log::info('catalog_import.brand_created_from_feed', ['brand' => $name, 'slug' => $slug]);

        return $brand->id;
    }

    /**
     * @param  array<string, string>  $categories
     * @param  array<string, string>  $categoryNames
     */
    private function resolveCategory(?string $value, array $categories, array $categoryNames): ?string
    {
        if ($value === null) {
            return null;
        }

        // Feeds name categories either way, so both are accepted — but only ones that exist.
        return $categories[Str::slug($value)]
            ?? $categories[$value]
            ?? $categoryNames[Str::lower($value)]
            ?? null;
    }

    private function recordPartNumber(Product $product, ImportRow $row): void
    {
        if ($row->partNumber === null) {
            return;
        }

        $normalised = ProductCrossReference::normalise($row->partNumber);

        if ($normalised === '') {
            return;
        }

        // Idempotent: re-running the same feed must not stack duplicate cross-references.
        DB::table('product_cross_references')->updateOrInsert(
            ['product_id' => $product->id, 'number_normalized' => $normalised],
            ['number' => $row->partNumber, 'type' => 'oem'],
        );
    }
}
