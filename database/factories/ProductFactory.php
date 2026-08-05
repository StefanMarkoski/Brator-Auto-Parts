<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductCondition;
use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = Str::title(fake()->words(3, true));
        // NET price — VAT is added at checkout.
        $priceMinor = fake()->numberBetween(29_900, 4_999_900);
        $stock = fake()->numberBetween(0, 120);

        return [
            'sku' => Str::upper(Str::random(3)).'-'.fake()->unique()->numerify('######'),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'brand_id' => Brand::factory(),
            'price_minor' => $priceMinor,
            'sale_price_minor' => fake()->boolean(20)
                ? (int) round($priceMinor * fake()->randomFloat(2, 0.6, 0.9))
                : null,
            'stock_quantity' => $stock,
            'stock_status' => $stock > 0 ? StockStatus::InStock : StockStatus::OutOfStock,
            'condition' => fake()->randomElement(ProductCondition::cases()),
            'weight_grams' => fake()->numberBetween(80, 25_000),
            'rating_avg' => fake()->randomFloat(1, 3.0, 5.0),
            'reviews_count' => fake()->numberBetween(0, 40),
            'is_active' => true,
            'published_at' => fake()->dateTimeBetween('-18 months', 'now'),
            'short_description' => fake()->sentence(16),
            'description' => fake()->paragraphs(4, true),
        ];
    }
}
