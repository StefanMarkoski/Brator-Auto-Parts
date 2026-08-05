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
