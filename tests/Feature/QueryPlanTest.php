<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Fitment\Models\VehicleVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asserts the query PLAN, not just the result.
 *
 * A result-only test passes just as happily when a change turns a range scan into a
 * full table scan. On a 150,000-row fitment table that difference is the whole
 * project, and it is invisible until production. These tests fail instead.
 */
final class QueryPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_parts_for_a_vehicle_uses_the_clustered_primary_key(): void
    {
        [$variant] = $this->seedFitment();

        $plan = $this->explain(
            'SELECT product_id FROM product_vehicle_fitments WHERE vehicle_variant_id = ?',
            [$variant->id]
        );

        $this->assertSame('PRIMARY', $plan->key,
            'The "parts for my car" query is no longer using the clustered primary key. '
            .'This is the hottest query in the shop; at real volume the difference is '
            .'a range scan versus a table scan.');
        $this->assertStringContainsString('Using index', (string) $plan->Extra);
    }

    public function test_which_vehicles_fit_a_part_uses_the_product_index(): void
    {
        [, $product] = $this->seedFitment();

        $plan = $this->explain(
            'SELECT vehicle_variant_id FROM product_vehicle_fitments WHERE product_id = ?',
            [$product->id]
        );

        $this->assertSame('pvf_product_index', $plan->key);
    }

    public function test_part_number_search_uses_the_normalised_index(): void
    {
        $product = Product::factory()->create();
        DB::table('product_cross_references')->insert([
            ['product_id' => $product->id, 'number' => 'A 000 989 82 01',
                'number_normalized' => 'A00098982 01', 'type' => 'oem', 'brand_hint' => null],
            ['product_id' => $product->id, 'number' => 'xyz-1234',
                'number_normalized' => 'XYZ1234', 'type' => 'competitor', 'brand_hint' => null],
        ]);

        $plan = $this->explain(
            'SELECT product_id FROM product_cross_references WHERE number_normalized = ?',
            ['XYZ1234']
        );

        $this->assertSame('pcr_number_normalized_index', $plan->key);
    }

    /**
     * Two variants and two products — never one. Laravel only stamps its lazy-loading
     * guard on results of 2+ rows, so single-row fixtures cannot catch an N+1.
     *
     * @return array{0: VehicleVariant, 1: Product}
     */
    private function seedFitment(): array
    {
        $variants = VehicleVariant::factory()->count(2)->create();
        $products = Product::factory()->count(2)->create();

        $rows = [];
        foreach ($variants as $variant) {
            foreach ($products as $product) {
                $rows[] = [
                    'vehicle_variant_id' => $variant->id,
                    'product_id' => $product->id,
                    'year_from' => null,
                    'year_to' => null,
                    'note' => null,
                ];
            }
        }
        DB::table('product_vehicle_fitments')->insert($rows);

        return [$variants->first(), $products->first()];
    }

    private function explain(string $sql, array $bindings): object
    {
        return DB::select('EXPLAIN '.$sql, $bindings)[0];
    }
}
