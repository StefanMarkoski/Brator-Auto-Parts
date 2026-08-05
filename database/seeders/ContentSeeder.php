<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Content is the thin context. Posts exist even though blog PAGES are out of MVP
 * scope, because the theme's homepage "Articles & Reviews" strip and the product
 * page's "Guide & Blog" block both render from them.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect(['Guides', 'Reviews', 'Maintenance', 'News'])
            ->map(fn (string $name, int $i) => PostCategory::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'position' => $i,
            ]));

        Post::factory()->count(12)->create([
            'post_category_id' => $categories->random()->id,
        ]);

        foreach (['About Us' => 'about', 'Contact Us' => 'contact-us'] as $title => $slug) {
            Page::create([
                'title' => $title,
                'slug' => $slug,
                'body' => '<p>Placeholder copy for the '.$title.' page.</p>',
                'is_published' => true,
            ]);
        }
    }
}
