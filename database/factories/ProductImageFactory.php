<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImage> */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            // Points at the theme's own placeholder images so seeded pages look real.
            'path' => 'assets/images/shop/shop-'.fake()->numberBetween(1, 12).'.png',
            'alt' => fake()->words(3, true),
            'position' => 0,
            'is_primary' => true,
        ];
    }
}
