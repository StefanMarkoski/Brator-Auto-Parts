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

    /**
     * Plausible distinguishing specs. ASCII only — an en-dash or a diameter symbol here
     * buys nothing and risks an encoding surprise somewhere down the stack.
     */
    private const SPECS = [
        'OE quality', 'Heavy Duty', '256mm', '288mm', '312mm',
        'vented', 'coated', 'Sport', '2-pin', '3-pin',
        'Left', 'Right', '12V', '24V', 'Long Life',
        'Premium', 'Eco', '4-hole', '5-hole', 'reinforced',
    ];

    /** Overridden by ProductSeederSmall so tests do not seed five thousand rows. */
    protected function productCount(): int
    {
        return 5_000;
    }

    /** Real filenames from the theme's own shop/ folder — invented ones 404. */
    private const IMAGES = [
        'product-01.jpg', 'product-02.jpg', 'product-03.jpg', 'product-04.jpg',
        'product-05.jpg', 'product-06.jpg', 'wheel-01.jpg', 'wheel-02.jpg',
        'wheel-03.jpg', 'wheel-04.jpg', 'wheel-05.jpg', 'wheel-06.jpg',
        'wheel-07.jpg', 'wheel-08.jpg', 'wheel-09.jpg', 'wheel-10.jpg',
    ];

    public function run(): void
    {
        $brandIds = Brand::query()->pluck('id')->all();
        $brandNames = Brand::query()->pluck('name', 'id')->all();
        $leafCategoryIds = Category::query()->where('depth', 1)->pluck('id')->all();
        $leafIdsBySlug = Category::query()->where('depth', 1)->pluck('id', 'slug')->all();
        $partsByCategory = VehicleData::partsByCategory();
        // Only the categories we have real part types for.
        $leafSlugs = array_values(array_intersect(
            array_keys($leafIdsBySlug), array_keys($partsByCategory)
        ));

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

                // Name, price band and category all come from the same real part list,
                // so a listing reads like a parts catalogue and every price is plausible
                // for what it is. A random 299–49.999 across every product made it
                // impossible to tell a sane result from a broken filter.
                $categorySlug = $leafSlugs[$n % count($leafSlugs)];
                $parts = $partsByCategory[$categorySlug];
                [$partType, $priceLow, $priceHigh] = $parts[$n % count($parts)];

                $brandId = $brandIds[$n % count($brandIds)];
                $brandName = $brandNames[$brandId];

                // A distinguishing spec in the name, so two products are never
                // identical on a listing. Cycling brand and part type alone produced
                // pairs of "Pierburg Brake Disc Front" with nothing to tell them apart,
                // which defeats the point of readable data.
                // The spec advances only after a full pass through the brands, so brand
                // and spec do not cycle in lockstep. Sharing a stride is what left 1,440
                // products with an identical name to another.
                // Spec is driven by the product's index WITHIN its category, so within a
                // category the spec advances by one each time while the brand advances by
                // a different stride. Their periods (20 and 9) multiply to 180, which is
                // more products than any one category holds — so no two products in a
                // category share a name. Earlier attempts had brand and spec cycling in
                // lockstep, which left thousands of identical names.
                $spec = self::SPECS[intdiv($n, count($leafSlugs)) % count(self::SPECS)];
                $name = "{$brandName} {$partType} {$spec}";

                $priceMinor = random_int($priceLow, $priceHigh);
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
                    'short_description' => "Direct-fit {$partType} by {$brandName}, built to OE tolerances.",
                    'description' => "<p><strong>{$name}</strong> &mdash; built to the manufacturer's specification.</p>"
                        .'<p>Use the vehicle filter to confirm this part fits your car before ordering. '
                        .'Cross-reference numbers are listed in the specification tab.</p>',
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
                // The part lands in the category its type actually belongs to.
                $primary = $leafIdsBySlug[$categorySlug];
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

        // Category counters, computed once at the end rather than per insert, and over
        // the SUBTREE rather than direct assignments only. Counting direct rows left every
        // parent department reading "0 parts" on the homepage while /shop/braking returned
        // 800-odd — the number a shopper saw and the number they got disagreed on the very
        // first click.
        //
        // A JOIN against a derived table, because MySQL refuses to reference an UPDATE's
        // own target table inside a subquery (error 1093).
        DB::statement('UPDATE categories SET products_count = 0');
        DB::statement("
            UPDATE categories c
            JOIN (
                SELECT parent.id AS id, COUNT(DISTINCT pc.product_id) AS total
                FROM categories parent
                JOIN categories child ON child.path LIKE CONCAT(parent.path, '%')
                JOIN product_categories pc ON pc.category_id = child.id
                GROUP BY parent.id
            ) counted ON counted.id = c.id
            SET c.products_count = counted.total
        ");

        $this->command->info('  seeded '.count($productIds).' products');
    }
}
