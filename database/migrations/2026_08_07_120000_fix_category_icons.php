<?php

declare(strict_types=1);

use App\Domain\Catalog\Queries\Public\GetNavigationQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Point every department at an icon that matches it.
 *
 * The seeder handed icons out by counting (`position % 18`), so Engine showed the alloy
 * wheel, Wheels & Tires showed the battery, Filters showed a tyre — and Lighting and
 * Interior showed the theme's grey "98X96" and "184X120" placeholder boxes, because the
 * theme only ships six real part icons for eight departments.
 *
 * A data migration rather than a re-seed: the shop already has orders and imports against
 * these rows, and this fixes existing data wherever it runs — the seeder alone would only
 * fix a database built from scratch.
 */
return new class extends Migration
{
    /** @var array<string, string> Kept in step with CatalogStructureSeeder::ICONS. */
    private const ICONS = [
        'braking' => 'assets/images/categories/categories-01.png',
        'wheels-tires' => 'assets/images/categories/categories-03.png',
        'engine' => 'app/images/categories/engine.png',
        'filters' => 'assets/images/categories/categories-06.png',
        'suspension' => 'app/images/categories/suspension.png',
        'electrical' => 'assets/images/categories/categories-02.png',
        'lighting' => 'assets/images/categories/categories-05.png',
        'interior' => 'app/images/categories/interior.png',
    ];

    public function up(): void
    {
        foreach (self::ICONS as $slug => $icon) {
            $parent = DB::table('categories')->where('slug', $slug)->first(['id']);

            if ($parent === null) {
                continue;
            }

            DB::table('categories')->where('id', $parent->id)->update(['image_path' => $icon]);
            // Subcategories follow their department, so none of them keeps a placeholder either.
            DB::table('categories')->where('parent_id', $parent->id)->update(['image_path' => $icon]);
        }

        /*
         | The mega menu caches the category tree for an hour, so without this the header kept
         | serving the old icons after the migration ran — which is exactly how a fix looks like
         | it did not work. Found by checking /shop after the homepage had already updated.
        */
        GetNavigationQuery::forget();
    }

    /**
     * Deliberately not reversible in kind.
     *
     * Rolling back would mean restoring icons that were wrong — a wheel on Engine and two
     * grey boxes on the homepage. There is nothing to gain by putting them back.
     */
    public function down(): void {}
};
