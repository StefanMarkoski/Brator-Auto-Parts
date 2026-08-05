<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'logo_path' => sprintf('assets/images/brand/brand-%02d.png', fake()->numberBetween(1, 18)),
            'description' => fake()->sentence(12),
            'is_active' => true,
            'position' => fake()->numberBetween(0, 50),
        ];
    }
}
