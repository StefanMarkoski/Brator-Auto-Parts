<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * The product seeder at test scale. Seeds enough rows to exercise the invariants
 * (and always more than one, so Laravel's lazy-loading guard is actually armed)
 * without spending five seconds per test.
 */
class ProductSeederSmall extends ProductSeeder
{
    protected function productCount(): int
    {
        return 40;
    }
}
