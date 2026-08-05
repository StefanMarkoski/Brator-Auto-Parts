<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries\Public;

use Illuminate\Support\Facades\DB;

/**
 * Which products have actually sold, most first.
 *
 * Cross-context READ API. Catalog was querying `receipt_lines` directly to build its
 * "Best Seller" strip, which the DDD spec forbids (§2: Queries/Public is the only way
 * another context may read from this one) — and the missing folder was the gate telling
 * me so.
 *
 * It matters beyond tidiness: what counts as a sale is Ordering's business. Today a
 * cancelled receipt still counts, and the day that changes it should change here, once,
 * rather than in whichever contexts happened to reach into the table.
 *
 * Returns ids rather than product data, so Catalog still owns how a product is shaped.
 */
final class GetBestSellingProductIdsQuery
{
    /** @return list<string> */
    public function execute(int $limit): array
    {
        return DB::table('receipt_lines')
            ->join('receipts', 'receipts.id', '=', 'receipt_lines.receipt_id')
            ->whereNotNull('receipt_lines.product_id')
            // Cancelled orders are not sales. Ordering's call to make, and now made in
            // one place instead of implicitly by whoever read the table.
            ->where('receipts.status', '!=', 'cancelled')
            ->select('receipt_lines.product_id', DB::raw('SUM(receipt_lines.quantity) AS units'))
            ->groupBy('receipt_lines.product_id')
            ->orderByDesc('units')
            ->limit($limit)
            ->pluck('receipt_lines.product_id')
            ->all();
    }
}
