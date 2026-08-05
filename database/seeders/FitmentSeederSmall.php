<?php

declare(strict_types=1);

namespace Database\Seeders;

/** The fitment seeder at test scale — enough rows to exercise the joins, not 150k. */
class FitmentSeederSmall extends FitmentSeeder
{
    protected function modelsPerMake(): int
    {
        return 1;
    }

    protected function variantsPerModel(): int
    {
        return 2;
    }

    protected function fitmentsPerProduct(): int
    {
        return 3;
    }
}
