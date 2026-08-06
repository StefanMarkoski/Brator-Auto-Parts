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
    /**
     * How many variants each product fits, as a fraction of the whole tree.
     *
     * The vehicle tree itself is fixed real data, so the only thing worth scaling for
     * tests is the fitment table — which is the one that gets large.
     */
    protected function fitmentSpanFraction(): float
    {
        return 0.25;
    }

    public function run(): void
    {
        $now = now();
        $tree = VehicleData::tree();

        // Real marques, models and engines. The generated version produced "Series 1"
        // through "Series 10" per make with random engine names, which made every filter
        // result indistinguishable from every other — you could not tell by looking
        // whether a filter had worked at all.
        $makeRows = [];
        foreach (array_keys($tree) as $i => $name) {
            $makeRows[] = [
                'name' => $name,
                'slug' => Str::slug($name),
                // NO LOGO. The seeder used to point every brand at one of the theme's own
                // brand images — which are other companies' actual logos, 18 of them shared
                // across 36 brands, so a Gates part displayed an "otyres" mark. Showing a real
                // third party's branding on somebody else's product is worse than showing
                // nothing, and the views fall back to the brand NAME when this is null.
                'logo_path' => null,
                'position' => $i,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('vehicle_makes')->insert($makeRows);
        $makeIds = DB::table('vehicle_makes')->pluck('id', 'name')->all();

        $modelRows = [];
        foreach ($tree as $makeName => $models) {
            foreach (array_keys($models) as $modelName) {
                $modelRows[] = [
                    'make_id' => $makeIds[$makeName],
                    'name' => $modelName,
                    'slug' => Str::slug($makeName.'-'.$modelName),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('vehicle_models')->insert($modelRows);

        $modelIds = [];
        foreach (DB::table('vehicle_models')->get(['id', 'make_id', 'name']) as $row) {
            $modelIds[$row->make_id.'|'.$row->name] = $row->id;
        }

        $variantRows = [];
        foreach ($tree as $makeName => $models) {
            foreach ($models as $modelName => $variants) {
                $modelId = $modelIds[$makeIds[$makeName].'|'.$modelName];

                foreach ($variants as [$subModel, $engineCode, $powerKw, $from, $to, $fuel]) {
                    $variantRows[] = [
                        'model_id' => $modelId,
                        'name' => $subModel,
                        'year_from' => $from,
                        'year_to' => $to,
                        'engine_code' => $engineCode,
                        'fuel_type' => $fuel,
                        'power_kw' => $powerKw,
                        'engine_cc' => (int) round($powerKw * 18 / 100) * 100,
                        'body_type' => 'Hatchback',
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }
        DB::table('vehicle_variants')->insert($variantRows);

        $variantIds = DB::table('vehicle_variants')->pluck('id')->all();
        $variantCount = count($variantIds);

        // Fitment. Products fit a contiguous SLICE of the variant list rather than a
        // random scatter, so "parts for my car" returns a coherent set a human can sanity
        // check — random fitment made every vehicle look the same.
        $productIds = DB::table('products')->pluck('id')->all();
        $fitments = [];
        $total = 0;
        $span = max(1, (int) round($variantCount * $this->fitmentSpanFraction()));

        foreach ($productIds as $i => $productId) {
            $start = ($i * 7) % $variantCount;

            for ($n = 0; $n < $span; $n++) {
                $variantId = $variantIds[($start + $n) % $variantCount];
                $narrow = ($i + $n) % 9 === 0;

                $fitments[] = [
                    'vehicle_variant_id' => $variantId,
                    'product_id' => $productId,
                    // A part often fits a variant for only part of its production run.
                    'year_from' => $narrow ? 2010 : null,
                    'year_to' => $narrow ? 2016 : null,
                    'note' => $narrow ? 'Facelift models only' : null,
                ];
            }

            if (count($fitments) >= 5_000) {
                DB::table('product_vehicle_fitments')->insertOrIgnore($fitments);
                $total += count($fitments);
                $fitments = [];
            }
        }

        if ($fitments !== []) {
            DB::table('product_vehicle_fitments')->insertOrIgnore($fitments);
            $total += count($fitments);
        }

        $this->command->info(
            '  seeded '.count($makeRows).' makes, '.count($modelRows).' models, '
            .$variantCount.' variants and '.$total.' fitment rows'
        );
    }
}
