<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The dynamic homepage, seeded in the theme's original order so the rendered page
 * still matches index-2.html exactly. Staff can reorder, hide, retitle, and rebind
 * these from admin; they cannot add a section type the theme has no markup for.
 */
class HomepageSeeder extends Seeder
{
    /** @var list<array{type: string, heading: ?string, collection: ?string}> */
    private const SECTIONS = [
        ['type' => 'hero_banner', 'heading' => null, 'collection' => null],
        ['type' => 'categories_strip', 'heading' => 'Shop by Categories', 'collection' => null],
        ['type' => 'whats_hot', 'heading' => "What's Hot", 'collection' => 'whats-hot'],
        ['type' => 'featured_makes', 'heading' => 'Shop by Make', 'collection' => null],
        ['type' => 'best_sellers', 'heading' => 'Best Seller', 'collection' => 'best-sellers'],
        ['type' => 'essential_items', 'heading' => 'Essential Items for New Car', 'collection' => 'essential-items'],
        ['type' => 'new_arrivals', 'heading' => 'New Arrivals', 'collection' => 'new-arrivals'],
        ['type' => 'articles', 'heading' => 'Articles & Reviews', 'collection' => null],
        ['type' => 'featured_brands', 'heading' => 'Featured Brands', 'collection' => null],
        ['type' => 'newsletter', 'heading' => null, 'collection' => null],
    ];

    public function run(): void
    {
        $now = now();
        $collections = DB::table('product_collections')->pluck('id', 'slug')->all();

        $rows = [];
        foreach (self::SECTIONS as $position => $spec) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'section_type' => $spec['type'],
                'heading' => $spec['heading'],
                'subheading' => null,
                'product_collection_id' => $spec['collection'] === null
                    ? null
                    : ($collections[$spec['collection']] ?? null),
                'settings' => null,
                'position' => $position,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('homepage_sections')->insert($rows);

        $banners = [];
        for ($i = 1; $i <= 3; $i++) {
            $banners[] = [
                'id' => (string) Str::ulid(),
                'placement' => 'home_hero',
                'title' => 'Up to 50% off selected parts',
                'subtitle' => 'Free delivery on orders over 3.000 ден',
                'image_path' => 'assets/images/slider/slider-'.$i.'.png',
                'mobile_image_path' => null,
                'link_url' => '/shop',
                'link_label' => 'Shop now',
                'position' => $i - 1,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('banners')->insert($banners);
    }
}
