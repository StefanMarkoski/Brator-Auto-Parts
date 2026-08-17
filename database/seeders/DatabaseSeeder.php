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

        /*
         | The admin account. Credentials read from the environment with the development
         | values as defaults, so nothing changes on a laptop — "password" is still
         | "password" here.
         |
         | Env-driven because this seeder is also how a hosted copy gets its first user, and
         | the admin panel is this app's ENTIRE security boundary: there is no customer
         | login, so this one account is all of it. Seeding a public site with a password
         | that is literally "password", under an email address printed in the git history,
         | is not a small oversight. .env.production.example carries the slots and
         | docs/DEPLOY.md does not let you past the step.
        */
        User::factory()->create([
            'name' => config('shop.admin.name'),
            'email' => config('shop.admin.email'),
            'password' => config('shop.admin.password'),
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
