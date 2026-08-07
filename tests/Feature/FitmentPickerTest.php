<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setting a part's fitment by hand, from the product screens.
 *
 * The feed's `fits` column is the bulk route; this is the one-part route, and it exists because
 * a part created in the panel was otherwise invisible the moment a shopper picked their car.
 *
 * The dangerous test here is the LAST one. The chosen list is rendered by Alpine, so a broken
 * admin bundle would post no vehicle ids at all — and a screen that syncs whatever it is given
 * would read that as "remove everything" and quietly destroy fitment on every save.
 */
final class FitmentPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class, FitmentSeederSmall::class]);
    }

    public function test_the_cascade_narrows_one_level_at_a_time(): void
    {
        $admin = $this->admin();

        // Not one list of every vehicle: 82 here, tens of thousands in a real catalogue.
        $years = $this->actingAs($admin)->getJson('/admin/vehicles/years')->assertOk()->json();
        $this->assertNotEmpty($years);

        $makes = $this->actingAs($admin)->getJson('/admin/vehicles/makes')->assertOk()->json();
        $make = collect($makes)->firstWhere('name', 'Volkswagen');
        $this->assertNotNull($make, 'No Volkswagen in the seeded vehicle tree.');

        $models = $this->actingAs($admin)
            ->getJson("/admin/vehicles/models/{$make['id']}")->assertOk()->json();
        $model = collect($models)->first();

        $subModels = $this->actingAs($admin)
            ->getJson("/admin/vehicles/sub-models/{$model['id']}")->assertOk()->json();
        $this->assertNotEmpty($subModels);

        $engines = $this->actingAs($admin)
            ->getJson("/admin/vehicles/engines/{$model['id']}?name=".urlencode($subModels[0]))
            ->assertOk()->json();

        // Down to a single row, which is the level fitment is actually recorded at.
        $this->assertNotEmpty($engines);
        $this->assertArrayHasKey('id', $engines[0]);
        $this->assertArrayHasKey('label', $engines[0]);
    }

    public function test_the_year_only_narrows_and_is_optional(): void
    {
        $admin = $this->admin();

        $all = $this->actingAs($admin)->getJson('/admin/vehicles/makes')->assertOk()->json();
        $in1999 = $this->actingAs($admin)->getJson('/admin/vehicles/makes?year=1999')->assertOk()->json();

        // A part that fits a model across its whole life should not need a year picked first,
        // so no year means no filter rather than no results.
        $this->assertGreaterThanOrEqual(count($in1999), count($all));
        $this->assertNotEmpty($all);
    }

    public function test_a_guest_cannot_read_the_vehicle_lookups(): void
    {
        foreach (['years', 'makes', 'models/1', 'sub-models/1', 'engines/1'] as $path) {
            $this->get("/admin/vehicles/{$path}")->assertRedirect('/admin/login');
        }
    }

    public function test_creating_a_part_with_vehicles_makes_it_findable_by_car(): void
    {
        $variant = $this->someVariant();

        $this->actingAs($this->admin())
            ->post('/admin/products', [
                ...$this->productFields(),
                'fitment_variant_ids' => [$variant],
            ])
            ->assertRedirect();

        $product = Product::query()->where('sku', 'PICK-001')->firstOrFail();

        $this->assertTrue(DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)
            ->where('vehicle_variant_id', $variant)
            ->exists());

        // The reason the control exists: it now shows up for that car.
        $this->withSession(['vehicle_variant_id' => $variant])
            ->get('/shop/brake-pads?per_page=96')
            ->assertOk()
            ->assertSee('Kangoo Test Pad', false);
    }

    public function test_creating_a_part_with_no_vehicles_says_it_will_not_be_findable(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/products', $this->productFields())
            ->assertRedirect();

        // Said out loud on the confirmation, because nothing else on the screen would mention
        // it and the part looks perfectly fine in the catalogue.
        $this->assertStringContainsString(
            'will not appear when a shopper filters by their car',
            (string) session('status')
        );
    }

    public function test_a_made_up_vehicle_id_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/products', [
                ...$this->productFields(),
                'fitment_variant_ids' => [999999],
            ])
            ->assertSessionHasErrors('fitment_variant_ids.0');

        $this->assertSame(0, Product::query()->where('sku', 'PICK-001')->count());
    }

    public function test_the_edit_screen_lists_the_vehicles_a_part_already_fits(): void
    {
        $product = Product::query()->firstOrFail();
        $variant = $this->someVariant();

        DB::table('product_vehicle_fitments')->insertOrIgnore([
            'product_id' => $product->id, 'vehicle_variant_id' => $variant,
            'year_from' => null, 'year_to' => null, 'note' => null,
        ]);

        $names = DB::table('vehicle_variants as v')
            ->join('vehicle_models as mo', 'mo.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'mo.make_id')
            ->where('v.id', $variant)
            ->first(['mk.name as make', 'mo.name as model', 'v.name as variant']);

        $html = $this->actingAs($this->admin())
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->getContent();

        // Spelled out, not an id: "48" tells staff nothing about which car they are removing.
        foreach ([$names->make, $names->model, $names->variant] as $part) {
            $this->assertStringContainsString($part, $html);
        }
    }

    public function test_removing_every_vehicle_on_the_edit_screen_really_removes_them(): void
    {
        $product = Product::query()->firstOrFail();
        $variant = $this->someVariant();

        DB::table('product_vehicle_fitments')->insertOrIgnore([
            'product_id' => $product->id, 'vehicle_variant_id' => $variant,
            'year_from' => null, 'year_to' => null, 'note' => null,
        ]);

        /*
         | The one place a sync is right. Everywhere else fitment is additive because a feed
         | cannot know what another source recorded — but here a person is looking at the whole
         | list and has decided it should be empty. Refusing to remove would make the control
         | a liar.
        */
        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", [
                ...$this->productFields(),
                'sku' => $product->sku,
                'fitment_managed' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(0, DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)->count());
    }

    public function test_a_save_without_the_picker_leaves_fitment_completely_alone(): void
    {
        $product = Product::query()->firstOrFail();

        // A product with real fitment, as a seeded one has.
        $before = DB::table('product_vehicle_fitments')->where('product_id', $product->id)->count();
        $this->assertGreaterThan(0, $before, 'Need a product with fitment for this test to mean anything.');

        /*
         | THE ONE THAT MATTERS. The chosen list is rendered by Alpine, so a broken admin bundle
         | posts no vehicle ids at all. A screen that synced whatever it was given would read
         | that as "remove everything" and destroy fitment on every save, silently, with the
         | page looking fine. The picker posts a marker from inside a <template> — which only
         | renders if Alpine ran — and without it fitment is not touched.
        */
        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", [
                ...$this->productFields(),
                'sku' => $product->sku,
                // No fitment_managed, and no ids: exactly what a dead bundle would send.
            ])
            ->assertRedirect();

        $this->assertSame($before, DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)->count(),
            'A save with the picker absent destroyed the fitment.');
    }

    public function test_adding_by_hand_does_not_wipe_what_a_feed_recorded(): void
    {
        $product = Product::query()->firstOrFail();
        $existing = DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)
            ->pluck('vehicle_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $extra = $this->someVariant();
        $wanted = array_values(array_unique([...$existing, $extra]));

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", [
                ...$this->productFields(),
                'sku' => $product->sku,
                'fitment_managed' => '1',
                'fitment_variant_ids' => $wanted,
            ])
            ->assertRedirect();

        // The screen submits the whole list it was showing, so keeping the feed's rows is a
        // matter of them being IN that list — which is why the edit screen renders them.
        $this->assertSame(
            count($wanted),
            DB::table('product_vehicle_fitments')->where('product_id', $product->id)->count()
        );
    }

    public function test_a_rejected_save_keeps_the_vehicles_that_were_just_added(): void
    {
        $product = Product::query()->firstOrFail();
        $variant = (int) DB::table('vehicle_variants')->orderByDesc('id')->value('id');

        $names = DB::table('vehicle_variants as v')
            ->join('vehicle_models as mo', 'mo.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'mo.make_id')
            ->where('v.id', $variant)
            ->first(['mk.name as make', 'mo.name as model', 'v.name as variant']);

        /*
         | THE ONE THAT LOOKS LIKE "IT WILL NOT STICK". A save rejected on some unrelated field
         | redisplayed the form with the STORED fitment: every text box kept what had been typed,
         | and the one control rendered by JavaScript quietly reverted to what was on file. The car
         | just added was gone with nothing saying so — indistinguishable from a broken picker.
         |
         | A blank name, so validation fails while the fitment field itself is perfectly valid.
        */
        $this->actingAs($this->admin())
            ->from("/admin/products/{$product->id}/edit")
            ->put("/admin/products/{$product->id}", [
                ...$this->productFields(),
                'name' => '',
                'sku' => $product->sku,
                'fitment_managed' => '1',
                'fitment_variant_ids' => [$variant],
            ])
            ->assertRedirect("/admin/products/{$product->id}/edit")
            ->assertSessionHasErrors('name');

        // Followed as its own request, which is what a browser does: the redisplayed form has to
        // read the flashed input, not the database.
        $html = $this->get("/admin/products/{$product->id}/edit")->assertOk()->getContent();

        foreach ([$names->make, $names->model, $names->variant] as $part) {
            $this->assertStringContainsString(e($part), $html,
                'A rejected save dropped the vehicle that had just been added.');
        }

        // And nothing was written, because the save really did fail.
        $this->assertSame(0, DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)->where('vehicle_variant_id', $variant)->count());
    }

    private function someVariant(): int
    {
        return (int) DB::table('vehicle_variants')->orderBy('id')->value('id');
    }

    /** @return array<string, mixed> */
    private function productFields(): array
    {
        return [
            'name' => 'Kangoo Test Pad',
            'sku' => 'PICK-001',
            'category_ids' => [Category::query()->where('slug', 'brake-pads')->value('id')],
            'condition' => 'new',
            'price_major' => '1900',
            'stock_quantity' => '8',
            'stock_status' => StockStatus::InStock->value,
            'is_active' => '1',
            'published' => '1',
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
