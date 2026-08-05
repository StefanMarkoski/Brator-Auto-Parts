<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fitment\Enums\FuelType;
use App\Domain\Fitment\Models\VehicleModel;
use App\Domain\Fitment\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleVariant> */
class VehicleVariantFactory extends Factory
{
    protected $model = VehicleVariant::class;

    public function definition(): array
    {
        $from = fake()->numberBetween(1998, 2022);

        return [
            'model_id' => VehicleModel::factory(),
            'name' => fake()->randomFloat(1, 1.0, 4.0).' '.fake()->randomElement(['TDI', 'TSI', 'CDI', 'HDi', 'dCi']),
            'year_from' => $from,
            // Null means still in production — a real state, not missing data.
            'year_to' => fake()->boolean(75) ? $from + fake()->numberBetween(3, 8) : null,
            'engine_code' => strtoupper(fake()->bothify('???###')),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'power_kw' => fake()->numberBetween(50, 300),
            'engine_cc' => fake()->numberBetween(1000, 4000),
            'body_type' => fake()->randomElement(['Hatchback', 'Saloon', 'Estate', 'SUV', 'Coupe']),
            'is_active' => true,
        ];
    }
}
