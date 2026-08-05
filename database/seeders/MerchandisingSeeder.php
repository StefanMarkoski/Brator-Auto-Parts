<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The homepage strips, the recommendation blocks, and reviews.
 *
 * The four collections here are exactly the theme's four homepage product strips.
 * "new-arrivals" and "best-sellers" are rules; the other two are staff-curated —
 * which is why product_collections has a `type` rather than four boolean columns
 * on products.
 */
class MerchandisingSeeder extends Seeder
{
    /** @var list<array{slug: string, name: string, type: string, rule: ?array<string, string>}> */
    private const COLLECTIONS = [
        ['slug' => 'whats-hot', 'name' => "What's Hot", 'type' => 'manual', 'rule' => null],
        ['slug' => 'best-sellers', 'name' => 'Best Seller', 'type' => 'automatic', 'rule' => ['order_by' => 'units_sold']],
        ['slug' => 'essential-items', 'name' => 'Essential Items for New Car', 'type' => 'manual', 'rule' => null],
        ['slug' => 'new-arrivals', 'name' => 'New Arrivals', 'type' => 'automatic', 'rule' => ['order_by' => 'published_at']],
    ];

    public function run(): void
    {
        $now = now();
        $productIds = DB::table('products')->inRandomOrder()->limit(400)->pluck('id')->all();

        $collectionIds = [];
        foreach (self::COLLECTIONS as $spec) {
            $id = (string) Str::ulid();
            $collectionIds[$spec['slug']] = $id;

            DB::table('product_collections')->insert([
                'id' => $id,
                'slug' => $spec['slug'],
                'name' => $spec['name'],
                'type' => $spec['type'],
                'rule' => $spec['rule'] === null ? null : json_encode($spec['rule']),
                'limit' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $items = [];
            foreach (array_slice($productIds, 0, 12) as $position => $productId) {
                $items[] = [
                    'product_collection_id' => $id,
                    'product_id' => $productId,
                    'position' => $position,
                ];
            }
            DB::table('product_collection_items')->insert($items);
            shuffle($productIds);
        }

        // Manual recommendation pairs.
        //
        // This is the reason product_recommendations has a `source` column: the
        // bought_together rows are meant to be COMPUTED from co-occurrence in
        // receipt_lines, but a shop with a fake checkout has no purchase history on
        // day one, so the block would render blank and the feature Stefan likes most
        // would look broken at launch. Manual pairs seed it; manual outranks computed;
        // the scheduled job takes over as real receipts accumulate.
        $recs = [];
        $pool = DB::table('products')->inRandomOrder()->limit(600)->pluck('id')->all();
        foreach (array_slice($pool, 0, 300) as $i => $productId) {
            $seen = [];
            for ($r = 0; $r < 4; $r++) {
                $related = $pool[array_rand($pool)];
                if ($related === $productId || isset($seen[$related])) {
                    continue;
                }
                $seen[$related] = true;

                $recs[] = [
                    'product_id' => $productId,
                    'related_product_id' => $related,
                    'type' => $r < 2 ? 'bought_together' : 'similar',
                    'source' => 'manual',
                    'score' => null,
                    'position' => $r,
                ];
            }
        }
        DB::table('product_recommendations')->insert($recs);

        // Reviews, so the theme's star ratings and review block have real content.
        $reviews = [];
        $bodies = [
            'Exact fit, arrived quickly and the finish is as described.',
            'Good value for the money. Bolted straight on with no modification.',
            'Quality feels close to the original part. Happy with it so far.',
            'Fitted to my car without issue, though the instructions were thin.',
        ];
        foreach (array_slice($pool, 0, 500) as $productId) {
            $count = random_int(1, 4);
            for ($n = 0; $n < $count; $n++) {
                $reviews[] = [
                    'id' => (string) Str::ulid(),
                    'product_id' => $productId,
                    'author_name' => 'Customer '.random_int(100, 999),
                    'author_email' => 'customer'.random_int(100, 999).'@example.com',
                    'rating' => random_int(3, 5),
                    'title' => 'Does the job',
                    'body' => $bodies[array_rand($bodies)],
                    'is_approved' => true,
                    'created_at' => $now->copy()->subDays(random_int(1, 300)),
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($reviews, 1_000) as $slice) {
            DB::table('product_reviews')->insert($slice);
        }

        // Rebuild the rating caches from the reviews just written, so the cached
        // columns agree with the ledger they summarise.
        DB::statement('
            UPDATE products p
            SET reviews_count = (
                    SELECT COUNT(*) FROM product_reviews r
                    WHERE r.product_id = p.id AND r.is_approved = 1
                ),
                rating_avg = COALESCE((
                    SELECT ROUND(AVG(r.rating), 1) FROM product_reviews r
                    WHERE r.product_id = p.id AND r.is_approved = 1
                ), 0)
        ');

        $this->command->info('  seeded '.count(self::COLLECTIONS).' collections, '.count($recs).' recommendations, '.count($reviews).' reviews');
    }
}
