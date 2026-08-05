<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * The fitment seeder at test scale.
 *
 * The vehicle tree is real data and cheap, so it is seeded in full — tests benefit from
 * recognisable makes and models too. Only the fitment table is scaled down, because that
 * is the one that reaches six figures.
 */
class FitmentSeederSmall extends FitmentSeeder
{
    protected function fitmentSpanFraction(): float
    {
        return 0.05;
    }
}
