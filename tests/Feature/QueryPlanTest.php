<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\DTOs\ProductFilter;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Queries\Internal\FilteredProductsQuery;
use App\Domain\Fitment\Models\VehicleVariant;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\ProductSeederSmall;
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
        // REWRITTEN after review. This used to EXPLAIN hand-written SQL that no code
        // path runs, against a 4-row table — where MySQL's plan means nothing anyway.
        //
        // Now it seeds enough fitment rows for the optimizer to have a real choice, and
        // EXPLAINs the SQL the APPLICATION actually builds, captured from the query log.
        $this->seedFitmentAtScale();

        $sql = $this->capture(function (): void {
            app(FilteredProductsQuery::class)->count(
                ProductFilter::fromArray([
                    'vehicleVariantId' => (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id'),
                ])
            );
        });

        $plans = collect(DB::select('EXPLAIN '.$sql));
        $fitment = $plans->firstWhere('table', 'product_vehicle_fitments');

        $this->assertNotNull($fitment, "The query did not touch product_vehicle_fitments:\n{$sql}");
        $this->assertSame('PRIMARY', $fitment->key,
            'The "parts for my car" lookup is no longer using the clustered primary key. '
            .'At real volume that is a range scan versus a table scan.');
        $this->assertStringContainsString('Using index', (string) $fitment->Extra);
    }

    public function test_a_category_and_attribute_filter_never_falls_back_to_a_table_scan(): void
    {
        $this->seedFitmentAtScale();

        $category = DB::table('categories')->where('depth', 1)->first();
        $attributeValue = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('a.code', 'origins')->value('pav.value_string');

        $sql = $this->capture(function () use ($category, $attributeValue): void {
            app(FilteredProductsQuery::class)->count(ProductFilter::fromArray([
                'categoryPath' => $category->path,
                'attributes' => ['origins' => [$attributeValue]],
            ]));
        });

        foreach (DB::select('EXPLAIN '.$sql) as $row) {
            $this->assertNotSame('ALL', $row->type,
                "Table {$row->table} is being scanned in full:\n{$sql}");
        }
    }

    /** The SQL the application actually built, with bindings inlined for EXPLAIN. */
    private function capture(callable $run): string
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        DB::disableQueryLog();

        $entry = collect(DB::getQueryLog())
            ->last(fn (array $q): bool => str_contains($q['query'], 'product'));

        $sql = $entry['query'];

        foreach ($entry['bindings'] as $binding) {
            $sql = preg_replace('/\?/', is_numeric($binding) ? (string) $binding : DB::getPdo()->quote((string) $binding), $sql, 1);
        }

        return $sql;
    }

    /**
     * Enough rows that the optimizer has a real decision to make. On a handful of rows
     * MySQL picks whatever it likes and the plan tells you nothing.
     */
    private function seedFitmentAtScale(): void
    {
        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);
        $this->seed(FitmentSeederSmall::class);

        $variants = DB::table('vehicle_variants')->pluck('id')->all();
        $products = DB::table('products')->pluck('id')->all();

        $rows = [];

        foreach ($products as $productId) {
            foreach (array_slice($variants, 0, 40) as $variantId) {
                $rows[] = [
                    'vehicle_variant_id' => $variantId,
                    'product_id' => $productId,
                    'year_from' => null, 'year_to' => null, 'note' => null,
                ];
            }
        }

        foreach (array_chunk($rows, 2_000) as $chunk) {
            DB::table('product_vehicle_fitments')->insertOrIgnore($chunk);
        }

        DB::statement('ANALYZE TABLE product_vehicle_fitments');
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
