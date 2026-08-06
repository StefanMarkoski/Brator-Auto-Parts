<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\CatalogImport\Models\ProductFieldOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records that a human now owns specific fields on a product.
 *
 * This is the enforcement half of "supplier import PLUS manual overrides". When staff
 * edit a field by hand we write it here, and the importer refuses to touch anything
 * listed. One rule in one place — the alternative, an `is_manual` boolean beside every
 * column, spreads the same rule across dozens of columns and gets forgotten on the
 * next column somebody adds.
 *
 * Only fields whose value ACTUALLY CHANGED are claimed. Opening a product, changing
 * one field and pressing save must not silently freeze every other field against
 * future imports.
 */
final class RecordFieldOverridesAction
{
    /**
     * Fields an import never supplies, so there is nothing to protect them from.
     *
     * Publishing is a merchandising decision by definition — a supplier feed has no opinion
     * about whether this shop is ready to sell a part. Claiming it would drop "published_at"
     * into the staff-owned list every time somebody toggled Published, which is noise in the
     * one place that has to stay readable.
     *
     * @var list<string>
     */
    public const NEVER_CLAIMED = ['published_at'];

    /**
     * @param  array<string, mixed>  $changes  field => new value
     * @return list<string> the fields newly claimed by a human
     */
    public function execute(Product $product, array $changes, ?string $userId): array
    {
        $claimed = [];

        DB::transaction(function () use ($product, $changes, $userId, &$claimed): void {
            foreach ($changes as $field => $newValue) {
                if (in_array($field, self::NEVER_CLAIMED, true)) {
                    continue;
                }

                if ($this->unchanged($product, $field, $newValue)) {
                    continue;
                }

                ProductFieldOverride::query()->updateOrCreate(
                    ['product_id' => $product->id, 'field_name' => $field],
                    ['overridden_by' => $userId, 'overridden_at' => now()],
                );

                $claimed[] = $field;
            }
        });

        if ($claimed !== []) {
            Log::info('catalog_import.record_field_overrides.claimed', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'fields' => $claimed,
            ]);
        }

        return $claimed;
    }

    private function unchanged(Product $product, string $field, mixed $newValue): bool
    {
        $current = $product->getRawOriginal($field);

        // Loose comparison on purpose: form input arrives as strings, columns are
        // typed, and "1900" === 1900 should not count as an edit.
        return (string) $current === (string) $newValue;
    }
}
