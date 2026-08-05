<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The vehicle tree and the compatibility table.
 *
 * The fitment count here is the point of this whole seeder: a bad index on the hot
 * path is invisible at a thousand rows and obvious at a hundred and fifty thousand.
 */
class FitmentSeeder extends Seeder
{
    /** Real marques, because "Make 01" tells you nothing when you click through. */
    private const MAKES = [
        'Volkswagen', 'BMW', 'Mercedes-Benz', 'Audi', 'Opel', 'Renault', 'Peugeot',
        'Citroen', 'Ford', 'Toyota', 'Nissan', 'Honda', 'Mazda', 'Hyundai', 'Kia',
        'Skoda', 'Seat', 'Fiat', 'Alfa Romeo', 'Volvo', 'Saab', 'Mitsubishi',
        'Subaru', 'Suzuki', 'Dacia', 'Lada', 'Chevrolet', 'Jeep', 'Land Rover',
        'Jaguar', 'Mini', 'Smart', 'Porsche', 'Lancia', 'SsangYong', 'Chrysler',
        'Iveco', 'Man', 'Isuzu', 'Tesla',
    ];

    /** Overridable so FitmentSeederSmall can seed test-scale volume. */
    protected function modelsPerMake(): int
    {
        return 10;
    }

    protected function variantsPerModel(): int
    {
        return 5;
    }

    protected function fitmentsPerProduct(): int
    {
        return 30;
    }

    public function run(): void
    {
        $now = now();

        $makeRows = [];
        foreach (self::MAKES as $i => $name) {
            $makeRows[] = [
                'name' => $name,
                'slug' => Str::slug($name),
                'logo_path' => sprintf('assets/images/brand/brand-%02d.png', ($i % 18) + 1),
                'position' => $i,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('vehicle_makes')->insert($makeRows);
        $makeIds = DB::table('vehicle_makes')->pluck('id')->all();

        $modelRows = [];
        foreach ($makeIds as $makeId) {
            for ($m = 1; $m <= $this->modelsPerMake(); $m++) {
                $name = 'Series '.$m;
                $modelRows[] = [
                    'make_id' => $makeId,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.$makeId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('vehicle_models')->insert($modelRows);
        $modelIds = DB::table('vehicle_models')->pluck('id')->all();

        $engines = ['1.6 TDI', '2.0 TDI', '1.4 TSI', '2.0 TSI', '1.5 dCi', '2.2 HDi', '3.0 CDI'];
        $fuels = ['petrol', 'diesel', 'hybrid', 'electric', 'lpg'];
        $bodies = ['Hatchback', 'Saloon', 'Estate', 'SUV', 'Coupe'];

        $variantRows = [];
        foreach ($modelIds as $modelId) {
            for ($v = 0; $v < $this->variantsPerModel(); $v++) {
                $from = random_int(1998, 2021);
                $variantRows[] = [
                    'model_id' => $modelId,
                    'name' => $engines[array_rand($engines)],
                    'year_from' => $from,
                    // Null = still in production. A real state, not missing data.
                    'year_to' => random_int(1, 100) <= 75 ? $from + random_int(3, 8) : null,
                    'engine_code' => strtoupper(Str::random(3)).random_int(100, 999),
                    'fuel_type' => $fuels[array_rand($fuels)],
                    'power_kw' => random_int(50, 300),
                    'engine_cc' => random_int(1000, 4000),
                    'body_type' => $bodies[array_rand($bodies)],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($variantRows, 1_000) as $slice) {
            DB::table('vehicle_models')->getConnection()->table('vehicle_variants')->insert($slice);
        }
        $variantIds = DB::table('vehicle_variants')->pluck('id')->all();
        $variantCount = count($variantIds);

        // The big one. Each product fits a spread of variants; the unique key is
        // (vehicle_variant_id, product_id) so duplicates must be avoided per product.
        $productIds = DB::table('products')->pluck('id')->all();
        $fitments = [];
        $total = 0;

        foreach ($productIds as $productId) {
            $picked = [];
            for ($f = 0; $f < $this->fitmentsPerProduct(); $f++) {
                $variantId = $variantIds[random_int(0, $variantCount - 1)];
                if (isset($picked[$variantId])) {
                    continue;
                }
                $picked[$variantId] = true;

                // Per-fitment years: a part often fits a variant for only part of its
                // production run, because a facelift changed a bracket. About a fifth
                // of real fitments are narrowed like this.
                $narrow = random_int(1, 100) <= 20;

                $fitments[] = [
                    'vehicle_variant_id' => $variantId,
                    'product_id' => $productId,
                    'year_from' => $narrow ? random_int(2005, 2015) : null,
                    'year_to' => $narrow ? random_int(2016, 2024) : null,
                    'note' => $narrow ? 'Facelift models only' : null,
                ];
            }

            if (count($fitments) >= 5_000) {
                DB::table('product_vehicle_fitments')->insert($fitments);
                $total += count($fitments);
                $fitments = [];
            }
        }

        if ($fitments !== []) {
            DB::table('product_vehicle_fitments')->insert($fitments);
            $total += count($fitments);
        }

        $this->command->info("  seeded {$variantCount} vehicle variants and {$total} fitment rows");
    }
}
