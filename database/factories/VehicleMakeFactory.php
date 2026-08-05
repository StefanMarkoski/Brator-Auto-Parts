<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fitment\Models\VehicleMake;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VehicleMake> */
class VehicleMakeFactory extends Factory
{
    protected $model = VehicleMake::class;

    public function definition(): array
    {
        $name = fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_path' => sprintf('assets/images/brand/brand-%02d.png', fake()->numberBetween(1, 18)),
            'position' => 0,
            'is_active' => true,
        ];
    }
}
