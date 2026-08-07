<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Fitment\Models\VehicleMake;
use App\Domain\Fitment\Models\VehicleModel;
use App\Domain\Fitment\Models\VehicleVariant;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adding a vehicle from the panel, and the claim the screen exists to prove.
 *
 * THE CLAIM: the shop's Year → Make → Model → Sub Model → Engine filter is not a fixed list in
 * code — it is a live query over three tables. Several tests here therefore do not stop at
 * "the row was written": they add a vehicle through the form and then walk the SHOPPER's picker
 * to the engine, because that walk is the thing being demonstrated to a client.
 *
 * The other half is the make and model being creatable in the same submit. Forcing staff to
 * create a make, save, create a model, save, and only then reach the vehicle is three screens
 * for one thought — and the trap that comes with allowing it is typing a make that already
 * exists, which must reuse it instead of growing a second Tesla.
 */
final class AdminVehicleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        /*
         | The homepage sections are seeded as well, unlike most fitment tests, because half of
         | these tests walk the SHOPPER's picker on "/" — and with no sections the homepage
         | renders no hero and therefore no picker at all, which would make the walk pass or fail
         | for reasons that have nothing to do with vehicles.
        */
        $this->seed([
            CatalogStructureSeeder::class,
            ProductSeederSmall::class,
            FitmentSeederSmall::class,
            MerchandisingSeeder::class,
            HomepageSeeder::class,
        ]);
    }

    public function test_a_new_make_model_and_vehicle_are_created_in_one_submit(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.vehicles.store'), [
                'make_id' => 'new',
                'make_name' => 'Tesla',
                'model_id' => 'new',
                'model_name' => 'Model 3',
                'name' => 'Long Range',
                'engine_code' => '3D0',
                'fuel_type' => 'electric',
                'power_kw' => 248,
                'year_from' => 2024,
                'is_active' => '1',
            ])
            /*
             | Searched for the make, not a bare page 1. The list is alphabetical and paginated,
             | so a Tesla added to a catalogue of Audis and Volkswagens landed on the last page:
             | the save worked, said so, and showed the operator nothing. Found by adding one in
             | the browser and then looking for it.
            */
            ->assertRedirect(route('admin.vehicles.index', ['q' => 'Tesla']))
            // Says which rows it made, because "Tesla was created" and "Tesla already existed"
            // are different facts and staff cannot see the difference otherwise.
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'the make Tesla')
                && str_contains($status, 'the model Model 3'));

        $make = VehicleMake::query()->where('name', 'Tesla')->sole();
        $model = VehicleModel::query()->where('name', 'Model 3')->sole();
        $variant = VehicleVariant::query()->where('name', 'Long Range')->sole();

        $this->assertSame('tesla', $make->slug);
        $this->assertSame($make->id, $model->make_id);
        $this->assertSame($model->id, $variant->model_id);
        // Blank last year means still in production, which the picker reads as "present".
        $this->assertNull($variant->year_to);
        $this->assertSame('electric', $variant->fuel_type->value);
    }

    public function test_the_shop_filter_offers_the_new_vehicle_without_any_further_work(): void
    {
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new',
            'make_name' => 'Tesla',
            'model_id' => 'new',
            'model_name' => 'Model 3',
            'name' => 'Long Range',
            'engine_code' => '3D0',
            'fuel_type' => 'electric',
            'year_from' => 2024,
            'year_to' => 2026,
        ])->assertSessionMissing('error');

        Auth::logout();

        $model = VehicleModel::query()->where('name', 'Model 3')->sole();
        $make = VehicleMake::query()->where('name', 'Tesla')->sole();

        /*
         | THE DEMONSTRATION, walked the way a shopper walks it. Nothing was rebuilt, no cache
         | was cleared and no seeder ran between the POST above and this — the picker's five
         | levels are queries, and the year list is a MIN/MAX over the variants.
        */
        $this->get('/')->assertOk()->assertSee('<option value="2025"', false);

        $this->post(route('vehicle.pick'), ['year' => 2025])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Tesla', false);

        $this->post(route('vehicle.pick'), ['year' => 2025, 'make' => $make->id])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Model 3', false);

        $this->post(route('vehicle.pick'), [
            'year' => 2025, 'make' => $make->id, 'model' => $model->id,
        ])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Long Range', false);

        // And the fifth dropdown, the engine — the level that identifies one actual car.
        $this->post(route('vehicle.pick'), [
            'year' => 2025, 'make' => $make->id, 'model' => $model->id, 'name' => 'Long Range',
        ])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('3D0', false);
    }

    public function test_a_year_outside_the_catalogue_becomes_offerable_once_a_vehicle_covers_it(): void
    {
        $ceiling = (int) date('Y') + 2;

        // Nobody offers this year yet: the dropdown is MIN(first year) to MAX(last year).
        $this->get('/')->assertOk()->assertDontSee('<option value="'.$ceiling.'"', false);

        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new', 'make_name' => 'Tesla',
            'model_id' => 'new', 'model_name' => 'Model 3',
            'name' => 'Long Range', 'year_from' => $ceiling,
        ])->assertSessionMissing('error');

        Auth::logout();

        $this->get('/')->assertOk()->assertSee('<option value="'.$ceiling.'"', false);
    }

    public function test_typing_a_make_that_already_exists_reuses_it(): void
    {
        $before = VehicleMake::query()->count();

        // Different case and padding on purpose: matched on the slug, so this is the same make.
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new', 'make_name' => '  volkswagen ',
            'model_id' => 'new', 'model_name' => 'Arteon',
            'name' => '2.0 TSI', 'year_from' => 2020,
        ])->assertSessionHas('status', fn (string $s): bool => ! str_contains($s, 'the make'));

        // No second Volkswagen, and the new model hangs off the one that was already there.
        $this->assertSame($before, VehicleMake::query()->count());
        $this->assertSame(
            VehicleMake::query()->where('slug', 'volkswagen')->value('id'),
            VehicleModel::query()->where('name', 'Arteon')->value('make_id')
        );
    }

    public function test_an_existing_make_can_take_a_new_model(): void
    {
        $make = VehicleMake::query()->where('slug', 'volkswagen')->sole();

        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => (string) $make->id,
            'model_id' => 'new', 'model_name' => 'ID.4',
            'name' => 'Pro Performance', 'year_from' => 2021,
        ])->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'the model ID.4')
            && ! str_contains($s, 'the make'));

        $this->assertSame($make->id, (int) VehicleModel::query()->where('name', 'ID.4')->value('make_id'));
    }

    public function test_a_model_from_another_make_is_refused(): void
    {
        $vw = VehicleMake::query()->where('slug', 'volkswagen')->sole();
        $otherModel = VehicleModel::query()->where('make_id', '!=', $vw->id)->firstOrFail();

        /*
         | The model list is filled per make in the browser, so a stale page can post a pairing
         | that never existed. findOrFail on the id alone would file the car under the wrong
         | marque and the picker would then offer, say, a Golf under Audi.
        */
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => (string) $vw->id,
            'model_id' => (string) $otherModel->id,
            'name' => 'Impossible', 'year_from' => 2020,
        ])->assertSessionHas('error');

        $this->assertSame(0, VehicleVariant::query()->where('name', 'Impossible')->count());
    }

    public function test_the_same_vehicle_cannot_be_added_twice(): void
    {
        $payload = [
            'make_id' => 'new', 'make_name' => 'Tesla',
            'model_id' => 'new', 'model_name' => 'Model 3',
            'name' => 'Long Range', 'engine_code' => '3D0', 'year_from' => 2024,
        ];

        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), $payload)
            ->assertSessionMissing('error');

        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), $payload)
            ->assertSessionHas('error', fn (string $e): bool => str_contains($e, 'already in the catalogue'));

        $this->assertSame(1, VehicleVariant::query()->where('name', 'Long Range')->count());
    }

    public function test_the_last_year_cannot_be_before_the_first(): void
    {
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new', 'make_name' => 'Tesla',
            'model_id' => 'new', 'model_name' => 'Model 3',
            'name' => 'Long Range', 'year_from' => 2024, 'year_to' => 2019,
        ])->assertSessionHas('error');

        $this->assertSame(0, VehicleVariant::query()->where('name', 'Long Range')->count());
    }

    public function test_a_wild_year_is_refused_because_it_would_swamp_the_dropdown(): void
    {
        /*
         | The picker's year list runs from the earliest first year to the latest last year, so a
         | single 1066 would hand every shopper a thousand-entry dropdown. Bounded on both sides.
        */
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new', 'make_name' => 'Tesla',
            'model_id' => 'new', 'model_name' => 'Model 3',
            'name' => 'Long Range', 'year_from' => 1066,
        ])->assertSessionHasErrors('year_from');

        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new', 'make_name' => 'Tesla',
            'model_id' => 'new', 'model_name' => 'Model 3',
            'name' => 'Long Range', 'year_from' => (int) date('Y') + 40,
        ])->assertSessionHasErrors('year_from');
    }

    public function test_a_vehicle_with_neither_a_chosen_nor_a_typed_make_is_refused(): void
    {
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'name' => 'Orphan', 'year_from' => 2020,
        ])->assertSessionHas('error', fn (string $e): bool => str_contains($e, 'make'));

        $this->assertSame(0, VehicleVariant::query()->where('name', 'Orphan')->count());
    }

    public function test_a_vehicle_can_be_hidden_from_the_filter_and_shown_again(): void
    {
        $variant = VehicleVariant::query()->firstOrFail();
        $model = VehicleModel::query()->findOrFail($variant->model_id);

        $this->actingAs($this->admin())
            ->put(route('admin.vehicles.update', $variant->id), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($variant->fresh()->is_active);

        Auth::logout();

        // Gone from the shopper's Sub Model dropdown, while the fitment rows pointing at it live.
        $this->post(route('vehicle.pick'), ['make' => $model->make_id, 'model' => $model->id]);
        $this->get('/')->assertOk()->assertDontSee('>'.e($variant->name).'<', false);

        $this->actingAs($this->admin())
            ->put(route('admin.vehicles.update', $variant->id), ['is_active' => '1'])
            ->assertRedirect();

        $this->assertTrue($variant->fresh()->is_active);
    }

    public function test_a_vehicle_that_parts_are_fitted_to_cannot_be_deleted(): void
    {
        $variantId = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');

        /*
         | The schema cascades fitment rows away with the vehicle, so this would silently strip
         | those parts of their fitment — the products survive and just stop being findable by
         | that car. Refused, with the count and the alternative said out loud.
        */
        $this->actingAs($this->admin())
            ->delete(route('admin.vehicles.destroy', $variantId))
            ->assertSessionHas('error', fn (string $e): bool => str_contains($e, 'Switch it off instead'));

        $this->assertNotNull(VehicleVariant::query()->find($variantId));
    }

    public function test_a_vehicle_with_no_parts_can_be_deleted(): void
    {
        $this->actingAs($this->admin())->post(route('admin.vehicles.store'), [
            'make_id' => 'new', 'make_name' => 'Tesla',
            'model_id' => 'new', 'model_name' => 'Model 3',
            'name' => 'Long Range', 'year_from' => 2024,
        ]);

        $variant = VehicleVariant::query()->where('name', 'Long Range')->sole();

        $this->actingAs($this->admin())
            ->delete(route('admin.vehicles.destroy', $variant->id))
            ->assertSessionHas('status');

        $this->assertNull(VehicleVariant::query()->find($variant->id));
        // The make and model it created are left alone: they may be about to take another car.
        $this->assertNotNull(VehicleMake::query()->where('name', 'Tesla')->first());
    }

    public function test_the_screen_reports_what_the_filter_currently_offers(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.vehicles.index'))->assertOk()->getContent();

        // The counts are the demonstration, so they have to be on the page and they have to be
        // the picker's own numbers rather than a total row count.
        $this->assertStringContainsString("What the shop's vehicle filter is offering right now", $html);
        $this->assertStringContainsString(
            number_format(VehicleVariant::query()->where('is_active', true)->count()),
            $html
        );
    }

    public function test_a_vehicle_leads_to_the_parts_that_fit_it(): void
    {
        $variantId = (int) DB::table('product_vehicle_fitments')->value('vehicle_variant_id');
        $fitting = DB::table('product_vehicle_fitments')->where('vehicle_variant_id', $variantId)
            ->pluck('product_id');
        $other = DB::table('products')->whereNotIn('id', $fitting)->value('id');

        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.index', ['fits' => $variantId]))
            ->assertOk()
            // Says what it is filtered by, and where fitment is actually edited. A filtered list
            // that does not announce its filter reads as "the catalogue has shrunk".
            ->assertSee('Parts that fit', false)
            ->assertSee('fitment is set per part', false)
            ->getContent();

        /*
         | The route IN from a vehicle, which is what was missing: fitment was editable per part
         | all along, but the only direction on offer was part → vehicles, so reaching the parts
         | for a car you had just added meant already knowing which parts they were.
        */
        $this->assertStringContainsString((string) $fitting->first(), $html);
        $this->assertStringNotContainsString((string) $other, $html);
    }

    public function test_the_product_list_says_how_many_cars_each_part_fits(): void
    {
        $bare = DB::table('products')
            ->whereNotIn('id', DB::table('product_vehicle_fitments')->select('product_id'))
            ->value('id');

        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.index'))->assertOk()->getContent();

        // Deep-linked to the Fitment card, because it sits below a long form and was being
        // missed — a control nobody can find is a control that does not exist.
        $this->assertStringContainsString('/edit#fitment', $html);

        if ($bare !== null) {
            $this->assertStringContainsString('No cars', $html,
                'A part that fits nothing should be called out: no shopper filtering by car can see it.');
        }
    }

    public function test_the_search_box_finds_a_vehicle_by_make_model_or_engine(): void
    {
        $variant = VehicleVariant::query()->whereNotNull('engine_code')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.vehicles.index', ['q' => $variant->engine_code]))
            ->assertOk()
            ->assertSee(e($variant->name), false);
    }

    public function test_a_guest_can_neither_add_nor_change_a_vehicle(): void
    {
        $variant = VehicleVariant::query()->firstOrFail();

        // Logged out explicitly: actingAs persists for the rest of a test, so without this the
        // "guest" would still be the admin and this would pass while proving nothing.
        Auth::logout();

        $this->get(route('admin.vehicles.index'))->assertRedirect('/admin/login');
        $this->post(route('admin.vehicles.store'), [])->assertRedirect('/admin/login');
        $this->put(route('admin.vehicles.update', $variant->id), ['is_active' => '0'])
            ->assertRedirect('/admin/login');
        $this->delete(route('admin.vehicles.destroy', $variant->id))->assertRedirect('/admin/login');

        $this->assertTrue($variant->fresh()->is_active);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
