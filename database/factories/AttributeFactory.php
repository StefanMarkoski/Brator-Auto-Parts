<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Enums\FilterWidget;
use App\Domain\Catalog\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'type' => AttributeType::Option,
            'unit' => null,
            'is_filterable' => true,
            'filter_widget' => FilterWidget::Checkbox,
            'position' => 0,
        ];
    }
}
