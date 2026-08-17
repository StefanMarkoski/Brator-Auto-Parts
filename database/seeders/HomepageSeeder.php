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
    /*
     | The hero slides, in the order they rotate.
     |
     | These are OUR pictures, committed to the repo under public/app/images/hero, not the
     | theme's stock sliders and not uploads. That is deliberate and worth explaining,
     | because it looks like the wrong place for them at first glance.
     |
     | They were originally added through the admin panel, which fetches a picture from a
     | URL and writes it to the uploads disk. On a host with an ephemeral filesystem that is
     | exactly the wrong place for the demo's own content: the files vanish on the next
     | deploy, and re-adding them means still having the source URLs a year later. As repo
     | assets they are in every deployment, permanently, with no bucket, no credentials and
     | nothing to remember — the same treatment app/images/categories already gets.
     |
     | The uploads disk is still the right home for anything a user adds AFTER deployment.
     | This is seed content, which is a different thing wearing the same clothes.
     |
     | 108 KB for all four.
    */
    private const HERO_IMAGES = [
        'app/images/hero/hero-1.jpg',
        'app/images/hero/hero-2.jpg',
        'app/images/hero/hero-3.jpg',
        'app/images/hero/hero-4.jpg',
    ];

    /** @var list<array{type: string, heading: ?string, collection: ?string}> */
    private const SECTIONS = [
        ['type' => 'hero_banner', 'heading' => null, 'collection' => null],
        ['type' => 'categories_strip', 'heading' => 'Shop by Categories', 'collection' => null],
        ['type' => 'whats_hot', 'heading' => "What's Hot", 'collection' => null],
        ['type' => 'featured_makes', 'heading' => 'Shop by Make', 'collection' => null],
        ['type' => 'best_sellers', 'heading' => 'Best Seller', 'collection' => 'best-sellers'],
        ['type' => 'essential_items', 'heading' => 'Essential Items for New Car', 'collection' => 'essential-items'],
        ['type' => 'new_arrivals', 'heading' => 'New Arrivals', 'collection' => 'new-arrivals'],
        // Hidden: blogs are out of scope, so there is nothing for it to render. Kept as a
        // row so it can be switched on from the homepage editor the day there is.
        ['type' => 'articles', 'heading' => 'Articles & Reviews', 'collection' => null, 'visible' => false],
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
                'is_visible' => $spec['visible'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('homepage_sections')->insert($rows);

        $banners = [];

        // What's Hot: four promo boxes. The theme gives each its own design class, and
        // the loop position picks it, so the four look as the theme author intended.
        $hot = [
            ['title' => "Helix\nEngine\nOils", 'subtitle' => 'Keep things running smoothly'],
            ['title' => "Brake\nPads &\nDiscs", 'subtitle' => 'Stop shorter, wear slower'],
            ['title' => "Alloy\nWheels", 'subtitle' => 'Fit your car, not just any car'],
            ['title' => "Filters\n& Service\nKits", 'subtitle' => 'Everything for a full service'],
        ];
        foreach ($hot as $i => $box) {
            $banners[] = [
                'id' => (string) Str::ulid(),
                'placement' => 'home_secondary',
                'title' => $box['title'],
                'subtitle' => $box['subtitle'],
                'image_path' => 'assets/images/hot/hot-'.($i + 1).'.png',
                'mobile_image_path' => null,
                'link_url' => '/shop',
                'link_label' => 'Shop Now',
                'position' => $i,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Driven by the list, not a hardcoded 3 — adding a fifth picture to HERO_IMAGES
        // should not also require finding this loop.
        foreach (self::HERO_IMAGES as $index => $heroImage) {
            $banners[] = [
                'id' => (string) Str::ulid(),
                'placement' => 'home_hero',
                'title' => 'Up to 50% off selected parts',
                'subtitle' => 'Free delivery on orders over 3.000 ден',
                'image_path' => $heroImage,
                'mobile_image_path' => null,
                'link_url' => '/shop',
                'link_label' => 'Shop now',
                'position' => $index,
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
