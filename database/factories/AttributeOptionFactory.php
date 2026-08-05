<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttributeOption> */
class AttributeOptionFactory extends Factory
{
    protected $model = AttributeOption::class;

    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'value' => fake()->unique()->word(),
            'swatch_hex' => null,
            'position' => 0,
        ];
    }
}
