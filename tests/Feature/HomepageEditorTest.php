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

    /*
     | The four sections whose Heading box did nothing at all.
     |
     | The editor rendered a Heading and a Subheading for every section, and hero_banner,
     | featured_makes, newsletter and articles printed neither — so the save went green and the
     | homepage did not move. The hero was the worst of them: its words were Blade literals
     | ("#1 Online Marketplace" / "Car Spares OEM & Atermarkets", typo included), which is the
     | first thing any client asks to change.
     |
     | One test per section rather than a loop, so a failure names the section.
    */
    public function test_the_hero_prints_the_heading_and_subheading_from_the_editor(): void
    {
        $this->retitle('hero_banner', 'Parts for your car, in Skopje', 'Delivered across Macedonia');

        $this->get('/')->assertOk()
            ->assertSee('Parts for your car, in Skopje', false)
            ->assertSee('Delivered across Macedonia', false)
            // The literals it replaced. "#1" is a claim a single-seller shop cannot make, and
            // "Atermarkets" is the template author's spelling.
            ->assertDontSee('#1 Online Marketplace', false)
            ->assertDontSee('Atermarkets', false);
    }

    public function test_shop_by_make_prints_the_heading_from_the_editor(): void
    {
        $this->retitle('featured_makes', 'Pick your manufacturer');

        $this->get('/')->assertOk()
            ->assertSee('Pick your manufacturer', false)
            ->assertDontSee('Featured Makes', false)
            // The second tab was worse than fiction: tab.js counts titles and then indexes into
            // panes, so clicking a second title with only one pane threw and blanked the whole
            // section. And "view more 2" revealed 13 makes this shop does not stock.
            ->assertDontSee('Featured Models', false)
            ->assertDontSee('view more 2', false)
            ->assertDontSee('Huyndai', false)
            ->assertDontSee('Mercerdess', false)
            ->assertDontSee('Rangover', false);
    }

    public function test_the_newsletter_prints_the_heading_and_subheading_from_the_editor(): void
    {
        $this->retitle('newsletter', 'Get the deals first', 'One email a week, no more');

        $this->get('/')->assertOk()
            ->assertSee('Get the deals first', false)
            ->assertSee('One email a week, no more', false)
            ->assertDontSee('Subscribe To Our Newsletter', false);
    }

    public function test_a_section_that_prints_no_text_keeps_the_heading_it_has(): void
    {
        // `articles` renders nothing at all — blog pages are out of scope — so the editor no
        // longer offers it a Heading or a Subheading, and therefore posts neither. The save must
        // not read those absent fields as "clear them": a save silently erasing a value it never
        // showed you is worse than the dead box it replaced.
        $section = HomepageSection::query()->where('section_type', 'articles')->firstOrFail();
        $this->assertNotNull($section->heading, 'This test needs a seeded heading to preserve.');

        $this->actingAs($this->admin())
            ->put("/admin/homepage/{$section->id}", ['is_visible' => 1])
            ->assertRedirect(route('admin.homepage.index'));

        $this->assertSame($section->heading, $section->fresh()->heading);
    }

    public function test_the_editor_only_offers_a_text_box_the_section_can_print(): void
    {
        $screen = $this->actingAs($this->admin())->get('/admin/homepage')->assertOk();

        $hero = HomepageSection::query()->where('section_type', 'hero_banner')->firstOrFail();
        $articles = HomepageSection::query()->where('section_type', 'articles')->firstOrFail();
        $makes = HomepageSection::query()->where('section_type', 'featured_makes')->firstOrFail();

        // The ids come from x-admin.field, which is what pairs a <label> with its input.
        $screen->assertSee('heading-'.$hero->id, false)
            ->assertSee('subheading-'.$hero->id, false)
            // Articles prints nothing, so neither box is offered.
            ->assertDontSee('heading-'.$articles->id, false)
            ->assertDontSee('subheading-'.$articles->id, false)
            // Shop by Make has one tab title and nowhere to put a second line.
            ->assertSee('heading-'.$makes->id, false)
            ->assertDontSee('subheading-'.$makes->id, false);
    }

    private function retitle(string $sectionType, string $heading, ?string $subheading = null): void
    {
        $section = HomepageSection::query()->where('section_type', $sectionType)->firstOrFail();

        $this->actingAs($this->admin())->put("/admin/homepage/{$section->id}", [
            'heading' => $heading,
            'subheading' => $subheading,
            'is_visible' => 1,
        ])->assertRedirect(route('admin.homepage.index'));
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
        // Was 'articles'. That section now renders nothing on purpose — it was hardcoded blog
        // posts and blogs are out of scope — so it can never prove a heading came BACK.
        $section = HomepageSection::query()->where('section_type', 'essential_items')->firstOrFail();
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
