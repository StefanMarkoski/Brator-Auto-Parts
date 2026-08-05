<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\ProductCrossReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Products at honest volume.
 *
 * Ten products make any schema look fast, so this seeds thousands and writes them
 * with chunked bulk inserts rather than Eloquent — 5,000 models firing events would
 * take minutes and prove nothing about the schema.
 */
class ProductSeeder extends Seeder
{
    private const CHUNK = 500;

    /** Overridden by ProductSeederSmall so tests do not seed five thousand rows. */
    protected function productCount(): int
    {
        return 5_000;
    }

    /** Real-ish part names, so seeded pages read like a parts shop. */
    private const NOUNS = [
        'Brake Disc', 'Brake Pad Set', 'Oil Filter', 'Air Filter', 'Timing Belt Kit',
        'Shock Absorber', 'Alloy Wheel', 'Battery', 'Alternator', 'Headlight Assembly',
        'Turbocharger', 'Water Pump', 'Clutch Kit', 'Radiator', 'Wheel Bearing',
        'Spark Plug Set', 'Fuel Pump', 'Control Arm', 'Cabin Filter', 'Starter Motor',
    ];

    /** Real filenames from the theme's own shop/ folder — invented ones 404. */
    private const IMAGES = [
        'product-01.jpg', 'product-02.jpg', 'product-03.jpg', 'product-04.jpg',
        'product-05.jpg', 'product-06.jpg', 'wheel-01.jpg', 'wheel-02.jpg',
        'wheel-03.jpg', 'wheel-04.jpg', 'wheel-05.jpg', 'wheel-06.jpg',
        'wheel-07.jpg', 'wheel-08.jpg', 'wheel-09.jpg', 'wheel-10.jpg',
    ];

    private const QUALIFIERS = ['Sport', 'Heavy Duty', 'Premium', 'OE Spec', 'Drilled', 'Slotted', 'Performance', 'Eco'];

    public function run(): void
    {
        $brandIds = Brand::query()->pluck('id')->all();
        $brandNames = Brand::query()->pluck('name', 'id')->all();
        $leafCategoryIds = Category::query()->where('depth', 1)->pluck('id')->all();

        $attributes = Attribute::query()->with('options')->get();
        $now = now();

        $productIds = [];

        $target = $this->productCount();

        for ($offset = 0; $offset < $target; $offset += self::CHUNK) {
            $products = [];
            $images = [];
            $pivots = [];
            $attrValues = [];
            $crossRefs = [];
            $movements = [];

            for ($i = 0; $i < self::CHUNK && ($offset + $i) < $target; $i++) {
                $n = $offset + $i;
                $id = (string) Str::ulid();
                $productIds[] = $id;

                $noun = self::NOUNS[$n % count(self::NOUNS)];
                $brandId = $brandIds[array_rand($brandIds)];
                $name = $brandNames[$brandId].' '.self::QUALIFIERS[$n % count(self::QUALIFIERS)].' '.$noun;

                $priceMinor = random_int(29_900, 4_999_900);
                $onSale = random_int(1, 100) <= 20;
                $stock = random_int(0, 120);

                $products[] = [
                    'id' => $id,
                    'sku' => 'BR-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.$n,
                    'brand_id' => $brandId,
                    'price_minor' => $priceMinor,
                    'sale_price_minor' => $onSale ? (int) round($priceMinor * 0.8) : null,
                    'stock_quantity' => $stock,
                    'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
                    'condition' => 'new',
                    'weight_grams' => random_int(80, 25_000),
                    'rating_avg' => round(random_int(30, 50) / 10, 1),
                    'reviews_count' => random_int(0, 40),
                    'is_active' => true,
                    'published_at' => $now->copy()->subDays(random_int(0, 540)),
                    'short_description' => "Direct-fit {$noun} built to OE tolerances. Sold as pictured.",
                    'description' => "<p>{$name}</p><p>Precision-engineered replacement part, tested to the manufacturer's specification. Check fitment against your vehicle before ordering.</p>",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $images[] = [
                    'id' => (string) Str::ulid(),
                    'product_id' => $id,
                    'path' => 'assets/images/shop/'.self::IMAGES[$n % count(self::IMAGES)],
                    'alt' => $name,
                    'position' => 0,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // One primary category, and sometimes a genuine second home — a brake
                // disc belongs under Braking AND Wheels & Hubs.
                $primary = $leafCategoryIds[array_rand($leafCategoryIds)];
                $pivots[] = ['product_id' => $id, 'category_id' => $primary, 'is_primary' => true, 'position' => 0];
                if (random_int(1, 100) <= 30) {
                    $secondary = $leafCategoryIds[array_rand($leafCategoryIds)];
                    if ($secondary !== $primary) {
                        $pivots[] = ['product_id' => $id, 'category_id' => $secondary, 'is_primary' => false, 'position' => 1];
                    }
                }

                foreach ($attributes as $attribute) {
                    if ($attribute->type === AttributeType::Number) {
                        $attrValues[] = [
                            'product_id' => $id,
                            'attribute_id' => $attribute->id,
                            'value_string' => null,
                            'value_number' => match ($attribute->code) {
                                'diameter' => random_int(14, 22),
                                'width' => random_int(6, 12),
                                default => random_int(-10, 55),
                            },
                            'attribute_option_id' => null,
                        ];
                    } else {
                        $option = $attribute->options->random();
                        $attrValues[] = [
                            'product_id' => $id,
                            'attribute_id' => $attribute->id,
                            'value_string' => $option->value,
                            'value_number' => null,
                            'attribute_option_id' => $option->id,
                        ];
                    }
                }

                // Messy, real-world number formats, so normalisation is actually tested.
                $oem = strtoupper(Str::random(1)).' '.random_int(100, 999).' '.random_int(100, 999).' '.random_int(10, 99);
                $crossRefs[] = [
                    'product_id' => $id,
                    'number' => $oem,
                    'number_normalized' => ProductCrossReference::normalise($oem),
                    'type' => 'oem',
                    'brand_hint' => $brandNames[$brandId],
                ];
                $competitor = strtolower(Str::random(3)).'-'.random_int(1000, 9999);
                $crossRefs[] = [
                    'product_id' => $id,
                    'number' => $competitor,
                    'number_normalized' => ProductCrossReference::normalise($competitor),
                    'type' => 'competitor',
                    'brand_hint' => null,
                ];

                // The opening stock movement, so the ledger reconciles with the cache
                // from the very first row rather than starting out inconsistent.
                $movements[] = [
                    'id' => (string) Str::ulid(),
                    'product_id' => $id,
                    'delta' => $stock,
                    'reason' => 'stocktake',
                    'reference_type' => null,
                    'reference_id' => null,
                    'note' => 'Opening balance',
                    'created_by' => null,
                    'created_at' => $now,
                ];
            }

            DB::table('products')->insert($products);
            DB::table('product_images')->insert($images);
            DB::table('product_categories')->insert($pivots);
            foreach (array_chunk($attrValues, 2_000) as $slice) {
                DB::table('product_attribute_values')->insert($slice);
            }
            DB::table('product_cross_references')->insert($crossRefs);
            DB::table('stock_movements')->insert($movements);
        }

        // Category counters, computed once at the end rather than per insert.
        DB::statement('
            UPDATE categories c
            SET products_count = (
                SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id
            )
        ');

        $this->command->info('  seeded '.count($productIds).' products');
    }
}
