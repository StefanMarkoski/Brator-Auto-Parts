<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductReview> */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_approved' => true,
        ];
    }
}
