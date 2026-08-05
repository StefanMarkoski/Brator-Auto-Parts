<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Content\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = Str::title(fake()->words(6, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'excerpt' => fake()->sentence(18),
            'body' => fake()->paragraphs(6, true),
            'cover_path' => sprintf('assets/images/blog/blog-%02d.jpg', fake()->numberBetween(1, 9)),
            'author_id' => null,
            'post_category_id' => null,
            'is_published' => true,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
