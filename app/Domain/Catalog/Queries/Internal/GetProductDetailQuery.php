<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Internal;

use App\Domain\Catalog\DTOs\ProductCardData;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GetProductDetailQuery
{
    public function __construct(private ListProductCardsQuery $cards) {}

    public function bySlug(string $slug): ?Product
    {
        return Product::query()
            // Eager loaded explicitly: the detail page touches all of these, and
            // preventLazyLoading turns a missing one into an error rather than an N+1.
            ->with([
                'brand',
                'images',
                'categories',
                'attributeValues.attribute',
                'attributeValues.option',
                'crossReferences',
                'reviews' => fn ($q) => $q->approved()->latest()->limit(10),
            ])
            ->where('slug', $slug)
            ->visible()
            ->first();
    }

    /**
     * "Frequently Bought Together" and "You May Also Like".
     *
     * Manual rows outrank computed ones, which is what keeps the block populated
     * before the shop has any purchase history to compute from.
     *
     * @return Collection<int, ProductCardData>
     */
    public function recommendations(string $productId, string $type, int $limit = 3): Collection
    {
        $ids = DB::table('product_recommendations')
            ->where('product_id', $productId)
            ->where('type', $type)
            ->orderByRaw("CASE source WHEN 'manual' THEN 0 ELSE 1 END")
            ->orderBy('position')
            ->limit($limit)
            ->pluck('related_product_id')
            ->all();

        return $this->cards->forIds($ids);
    }

    /** The vehicles a part fits, grouped for the fitment table on the detail page. */
    public function fitments(string $productId, int $limit = 40): Collection
    {
        return DB::table('product_vehicle_fitments as f')
            ->join('vehicle_variants as v', 'v.id', '=', 'f.vehicle_variant_id')
            ->join('vehicle_models as m', 'm.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'm.make_id')
            ->where('f.product_id', $productId)
            ->orderBy('mk.name')->orderBy('m.name')
            ->limit($limit)
            ->select([
                'mk.name as make', 'm.name as model', 'v.name as variant',
                'v.engine_code', 'v.year_from', 'v.year_to',
                'f.year_from as fit_from', 'f.year_to as fit_to', 'f.note',
            ])
            ->get();
    }
}
