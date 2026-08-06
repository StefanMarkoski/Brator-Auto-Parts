<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\ProductCrossReference;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invariants the seeded catalogue must hold. Fabricated data that is not a valid
 * domain state produces bugs that look like application bugs.
 */
final class SeededDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_part_number_normalisation_survives_real_world_formatting(): void
    {
        // The whole reason the column exists: these are the same part number.
        $this->assertSame(
            ProductCrossReference::normalise('A 000 989 82 01'),
            ProductCrossReference::normalise('a000989820-1')
        );
        $this->assertSame('A0009898201', ProductCrossReference::normalise('A 000 989 82 01'));
        $this->assertSame('XYZ1234', ProductCrossReference::normalise('xyz-1234'));
        $this->assertSame('ABC123', ProductCrossReference::normalise('  a.b/c 123  '));
    }

    public function test_every_product_has_exactly_one_primary_category(): void
    {
        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);

        $offenders = DB::select('
            SELECT product_id, SUM(is_primary) AS primaries
            FROM product_categories
            GROUP BY product_id
            HAVING primaries <> 1
        ');

        $this->assertSame([], $offenders,
            'is_primary drives the canonical URL and breadcrumb, so exactly one per '
            .'product — zero means no breadcrumb, two means two canonical URLs.');
    }

    public function test_no_product_is_filed_outside_its_own_department(): void
    {
        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);

        /*
         | FOUND BY BROWSING, not by reading code.
         |
         | The Braking listing was showing a fog light, a cabin filter and an alloy wheel. The
         | query was correct and the DATA was wrong: the seeder gave 30% of products a second
         | category picked at random from EVERY leaf in the tree, so "INA Fog Light Front
         | Premium" genuinely lived at /braking/brake-calipers/.
         |
         | Fabricated data has to be a state the business could actually be in. A shopper
         | cannot tell bad seed data from a broken filter — both look like the shop is wrong.
        */
        $offenders = DB::select("
            SELECT p.name, GROUP_CONCAT(c.path ORDER BY c.path) AS paths
            FROM products p
            JOIN product_categories pc ON pc.product_id = p.id
            JOIN categories c ON c.id = pc.category_id
            GROUP BY p.id, p.name
            HAVING COUNT(DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(c.path, '/', 2), '/', -1)) > 1
            LIMIT 10
        ");

        $described = array_map(
            fn (object $row): string => "{$row->name} => {$row->paths}",
            $offenders,
        );

        $this->assertSame([], $described,
            'These products are filed in more than one department, so they show up in listings '
            ."they have no business being in:\n  ".implode("\n  ", $described));
    }

    public function test_every_seeded_image_path_points_at_a_file_that_exists(): void
    {
        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);
        $this->seed(FitmentSeederSmall::class);
        $this->seed(MerchandisingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(ContentSeeder::class);

        $paths = collect()
            ->merge(DB::table('product_images')->distinct()->pluck('path'))
            ->merge(DB::table('brands')->whereNotNull('logo_path')->distinct()->pluck('logo_path'))
            ->merge(DB::table('vehicle_makes')->whereNotNull('logo_path')->distinct()->pluck('logo_path'))
            ->merge(DB::table('categories')->whereNotNull('image_path')->distinct()->pluck('image_path'))
            ->merge(DB::table('banners')->distinct()->pluck('image_path'))
            ->merge(DB::table('posts')->whereNotNull('cover_path')->distinct()->pluck('cover_path'))
            ->unique();

        $missing = $paths->reject(fn (string $path) => is_file(public_path($path)))->values()->all();

        // Seeded paths were originally invented from a count ("brand-1.png" when the
        // theme ships "brand-01.png", "shop-1.png" when it ships "product-01.jpg"), and
        // the result was a shop full of broken images that still returned 200. Guessing
        // a filename range is the bug this test exists to stop.
        $this->assertSame([], $missing,
            "These seeded image paths do not exist in public/:\n  ".implode("\n  ", $missing));
    }

    public function test_cached_stock_quantity_matches_the_movement_ledger(): void
    {
        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);

        $drift = DB::select('
            SELECT p.id, p.stock_quantity, COALESCE(SUM(m.delta), 0) AS ledger
            FROM products p
            LEFT JOIN stock_movements m ON m.product_id = p.id
            GROUP BY p.id, p.stock_quantity
            HAVING p.stock_quantity <> ledger
        ');

        $this->assertSame([], $drift,
            'products.stock_quantity is a cache of stock_movements. If it drifts, the '
            .'shop is lying about availability and nobody can explain why.');
    }
}
