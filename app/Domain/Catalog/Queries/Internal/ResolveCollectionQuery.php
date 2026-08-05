<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Internal;

use App\Domain\Catalog\DTOs\ProductCardData;
use App\Domain\Catalog\Models\ProductCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a product_collections row into actual cards.
 *
 * This is where the `type` column earns its place: a manual collection reads its
 * curated items in their pinned order, an automatic one runs its rule. Staff can flip
 * a strip between the two without a migration, and adding a strip is a seeder row.
 */
final class ResolveCollectionQuery
{
    public function __construct(private ListProductCardsQuery $cards) {}

    /** @return Collection<int, ProductCardData> */
    public function execute(?ProductCollection $collection): Collection
    {
        if ($collection === null || ! $collection->is_active) {
            return collect();
        }

        $limit = max(1, $collection->limit);

        if ($collection->type === 'automatic') {
            return match ($collection->rule['order_by'] ?? null) {
                'units_sold' => $this->cards->bestSelling($limit),
                'published_at' => $this->cards->newest($limit),
                // An unrecognised rule returns nothing rather than guessing. A silently
                // wrong strip is harder to notice than an empty one.
                default => collect(),
            };
        }

        $ids = DB::table('product_collection_items')
            ->where('product_collection_id', $collection->id)
            ->orderBy('position')
            ->limit($limit)
            ->pluck('product_id')
            ->all();

        return $this->cards->forIds($ids);
    }
}
