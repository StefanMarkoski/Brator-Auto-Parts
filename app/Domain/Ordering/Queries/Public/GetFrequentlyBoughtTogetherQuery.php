<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries\Public;

use Illuminate\Support\Facades\DB;

/**
 * What people actually bought alongside this part, most often first.
 *
 * Cross-context READ API, like its sibling GetBestSellingProductIdsQuery: receipts are
 * Ordering's, and Catalog may only read them through this folder.
 *
 * The product page's "Frequently Bought Together" strip used to come from
 * `product_recommendations`, a seeded table — so it was a real-looking widget presenting
 * invented pairings. Now it is a count over receipt lines: for the receipts this part appears
 * on, which other parts appear on those same receipts, ranked by how many receipts each shares.
 *
 * Sparse on purpose, and honest about it. 590 of 5,000 products currently have a companion, so
 * on most pages the caller gets nothing back and the section is hidden. A strip that says
 * "frequently bought together" about a pairing that has never happened is worse than no strip.
 */
final class GetFrequentlyBoughtTogetherQuery
{
    /**
     * @return list<string> product ids, the most-shared first
     */
    public function execute(string $productId, int $limit = 5): array
    {
        return DB::table('receipt_lines as bought')
            /*
             | Self-join on the receipt: every OTHER line of every receipt this part is on.
             | The != on product_id is what stops a part being its own companion — without it
             | the top result is always itself, on every product page.
            */
            ->join('receipt_lines as alongside', function ($join): void {
                $join->on('alongside.receipt_id', '=', 'bought.receipt_id')
                    ->whereColumn('alongside.product_id', '!=', 'bought.product_id');
            })
            ->join('receipts', 'receipts.id', '=', 'bought.receipt_id')
            ->where('bought.product_id', $productId)
            /*
             | product_id is nullable and ON DELETE SET NULL — a receipt line outlives the
             | product it sold. Those lines still state what was bought, but there is nothing
             | left to recommend, so they are skipped rather than counted into a null group.
            */
            ->whereNotNull('alongside.product_id')
            // Cancelled orders are not purchases. Ordering's call, made here once, the same way
            // the best-sellers query makes it.
            ->where('receipts.status', '!=', 'cancelled')
            ->groupBy('alongside.product_id')
            ->select('alongside.product_id', DB::raw('COUNT(DISTINCT bought.receipt_id) AS orders'))
            ->orderByDesc('orders')
            // A tiebreaker, because plenty of pairs share exactly one receipt and MySQL is
            // otherwise free to order those differently between requests — so the strip would
            // reshuffle itself on every reload.
            ->orderBy('alongside.product_id')
            ->limit($limit)
            ->pluck('alongside.product_id')
            ->all();
    }

    /**
     * How many orders back the strip, so the page can say where the pairing came from.
     *
     * Distinct receipts, not lines: two of the same part on one order is one order.
     */
    public function ordersBehind(string $productId): int
    {
        return DB::table('receipt_lines as bought')
            ->join('receipt_lines as alongside', function ($join): void {
                $join->on('alongside.receipt_id', '=', 'bought.receipt_id')
                    ->whereColumn('alongside.product_id', '!=', 'bought.product_id');
            })
            ->join('receipts', 'receipts.id', '=', 'bought.receipt_id')
            ->where('bought.product_id', $productId)
            ->whereNotNull('alongside.product_id')
            ->where('receipts.status', '!=', 'cancelled')
            ->distinct()
            ->count('bought.receipt_id');
    }
}
