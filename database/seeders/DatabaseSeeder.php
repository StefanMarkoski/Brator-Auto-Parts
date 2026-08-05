<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Bulk seeding does not need the query log, and keeping 200k+ statements in
        // memory is how a seeder dies with an allocation error rather than an error
        // you can act on.
        DB::disableQueryLog();

        User::factory()->create([
            'name' => 'Stefan Markoski',
            'email' => 'stefan.m@xgate.io',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $this->call([
            CatalogStructureSeeder::class,
            ProductSeeder::class,
            FitmentSeeder::class,
            MerchandisingSeeder::class,
            HomepageSeeder::class,
            ReceiptSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
