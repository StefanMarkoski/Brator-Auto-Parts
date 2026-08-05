<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fitment\Models\VehicleMake;
use App\Domain\Fitment\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VehicleModel> */
class VehicleModelFactory extends Factory
{
    protected $model = VehicleModel::class;

    public function definition(): array
    {
        $name = Str::title(fake()->word());

        return [
            'make_id' => VehicleMake::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ];
    }
}
