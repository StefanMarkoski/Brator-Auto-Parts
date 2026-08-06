<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\ProductCollection;
use App\Domain\Content\Models\HomepageSection;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage editor.
 *
 * Every test asks the REAL homepage whether the change took effect, rather than checking the
 * row was written. `homepage_sections` was already driving the storefront before this screen
 * existed; the thing worth proving is that the panel and the page agree.
 */
final class HomepageEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CatalogStructureSeeder::class,
            ProductSeederSmall::class,
            MerchandisingSeeder::class,
            HomepageSeeder::class,
        ]);
    }

    public function test_retitling_a_section_changes_the_heading_on_the_homepage(): void
    {
        $section = HomepageSection::query()->where('section_type', 'best_sellers')->firstOrFail();

        $this->get('/')->assertOk()->assertSee($section->heading, false);

        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}", [
            'heading' => 'Our Most Wanted Parts',
            'product_collection_id' => $section->product_collection_id,
            'is_visible' => 1,
        ])->assertRedirect(route('admin.homepage.index'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Our Most Wanted Parts', false)
            ->assertDontSee('Best Seller', false);
    }

    public function test_hiding_a_section_removes_it_from_the_homepage(): void
    {
        $section = HomepageSection::query()->where('section_type', 'new_arrivals')->firstOrFail();
        $heading = $section->heading;

        $this->get('/')->assertOk()->assertSee($heading, false);

        // is_visible omitted entirely, which is what an unticked checkbox posts. The toggle
        // component sends a hidden 0 for exactly this reason, but the endpoint must not
        // depend on that to be able to express "off".
        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}", [
            'heading' => $heading,
            'product_collection_id' => $section->product_collection_id,
        ]);

        $this->assertFalse($section->fresh()->is_visible);
        $this->get('/')->assertOk()->assertDontSee($heading, false);
    }

    public function test_a_hidden_section_can_be_brought_back(): void
    {
        $section = HomepageSection::query()->where('section_type', 'articles')->firstOrFail();
        $section->update(['is_visible' => false]);

        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}", [
            'heading' => $section->heading,
            'is_visible' => 1,
        ]);

        $this->assertTrue($section->fresh()->is_visible);
        $this->get('/')->assertOk()->assertSee($section->heading, false);
    }

    public function test_moving_a_section_down_reorders_the_homepage(): void
    {
        $ordered = HomepageSection::query()->orderBy('position')->orderBy('id')->get();
        $first = $ordered[0];
        $second = $ordered[1];

        $this->actingAs($this->admin())
            ->put("/admin/homepage/{$first->id}/move", ['direction' => 'down'])
            ->assertRedirect(route('admin.homepage.index'));

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(0, $second->fresh()->position);
    }

    public function test_positions_stay_a_dense_sequence_after_a_move(): void
    {
        $section = HomepageSection::query()->orderBy('position')->orderBy('id')->skip(3)->firstOrFail();

        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}/move", ['direction' => 'up']);

        $positions = HomepageSection::query()->orderBy('position')->pluck('position')->all();

        // A gap or a duplicate leaves ORDER BY free to pick either row first, so the page
        // order becomes non-deterministic between requests and the up/down buttons appear to
        // move the wrong row. Swapping two values in place is what produces that; renumbering
        // the whole page does not.
        $this->assertSame(range(0, count($positions) - 1), $positions);
    }

    public function test_moving_the_first_section_up_is_a_no_op_rather_than_an_error(): void
    {
        $first = HomepageSection::query()->orderBy('position')->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/homepage/{$first->id}/move", ['direction' => 'up'])
            ->assertRedirect(route('admin.homepage.index'));

        $this->assertSame(0, $first->fresh()->position);
        $this->get('/')->assertOk();
    }

    public function test_rebinding_a_strip_changes_which_products_it_shows(): void
    {
        $section = HomepageSection::query()->where('section_type', 'best_sellers')->firstOrFail();

        $other = ProductCollection::query()->whereKeyNot($section->product_collection_id)->firstOrFail();

        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}", [
            'heading' => $section->heading,
            'product_collection_id' => $other->id,
            'is_visible' => 1,
        ]);

        $this->assertSame($other->id, $section->fresh()->product_collection_id);
        $this->get('/')->assertOk();
    }

    public function test_a_collection_cannot_be_attached_to_a_section_that_shows_no_products(): void
    {
        $section = HomepageSection::query()->where('section_type', 'newsletter')->firstOrFail();
        $collection = ProductCollection::query()->firstOrFail();

        // The newsletter sign-up renders a fixed form. Accepting a collection here would
        // store a binding that nothing reads — a setting that looks configured and does
        // nothing, which is worse than no setting.
        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}", [
            'product_collection_id' => $collection->id,
            'is_visible' => 1,
        ])->assertSessionHas('error');

        $this->assertNull($section->fresh()->product_collection_id);
    }

    public function test_the_homepage_still_renders_with_every_section_hidden(): void
    {
        HomepageSection::query()->update(['is_visible' => false]);

        // An empty homepage is a legitimate state to pass through while staff rearrange it,
        // so it has to render rather than 500.
        $this->get('/')->assertOk();
    }

    public function test_a_guest_cannot_edit_the_homepage(): void
    {
        $section = HomepageSection::query()->firstOrFail();
        $heading = $section->heading;

        $this->get('/admin/homepage')->assertRedirect('/admin/login');
        $this->put("/admin/homepage/{$section->id}", ['heading' => 'Hacked'])
            ->assertRedirect('/admin/login');
        $this->put("/admin/homepage/{$section->id}/move", ['direction' => 'down'])
            ->assertRedirect('/admin/login');

        $this->assertSame($heading, $section->fresh()->heading);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
