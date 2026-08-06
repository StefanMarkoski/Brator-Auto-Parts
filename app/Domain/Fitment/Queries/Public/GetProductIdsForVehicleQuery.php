<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Queries\Public;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which products fit a given vehicle variant.
 *
 * Cross-context READ API. Catalog's filter and product page were querying
 * `product_vehicle_fitments` directly. Fitment owns what "fits" means — including the
 * per-fitment year narrowing, which a caller reaching into the table would have to
 * remember to apply and would eventually forget.
 */
final class GetProductIdsForVehicleQuery
{
    /** @return list<string> */
    public function execute(int $vehicleVariantId): array
    {
        return DB::table('product_vehicle_fitments')
            ->where('vehicle_variant_id', $vehicleVariantId)
            ->pluck('product_id')
            ->all();
    }

    /**
     * Does this one product fit this one vehicle?
     *
     * The product page needs the single-product answer, and it needs it to be the SAME
     * definition the listing filter uses. It previously asked nobody: the page printed
     * "This product fit for your vehicle" unconditionally, for every product, whether or
     * not a car had even been chosen — telling a shopper with a Golf that a Sprinter part
     * fits it, which is the one claim a parts shop cannot afford to get wrong.
     */
    public function fits(string $productId, int $vehicleVariantId): bool
    {
        return DB::table('product_vehicle_fitments')
            ->where('vehicle_variant_id', $vehicleVariantId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * The same question as a subquery, so a caller can intersect without loading tens of
     * thousands of ids into PHP first. Still Fitment's SQL — the shape of the question
     * stays here even when the execution is someone else's query.
     */
    public function subqueryFor(int $vehicleVariantId): callable
    {
        return function (Builder $query) use ($vehicleVariantId): void {
            $query->select('product_id')
                ->from('product_vehicle_fitments')
                ->where('vehicle_variant_id', $vehicleVariantId);
        };
    }
}
