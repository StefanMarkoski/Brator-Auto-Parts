<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(14),
            'image_path' => 'assets/images/categories/categories-'.fake()->numberBetween(1, 17).'.png',
            'path' => '/'.Str::slug($name).'/',
            'depth' => 0,
            'position' => 0,
            'is_active' => true,
            'products_count' => 0,
        ];
    }
}
