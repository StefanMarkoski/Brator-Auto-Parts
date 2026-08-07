<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Actions\SaveWhatsHotBoxAction;
use App\Domain\Content\Models\Banner;
use App\Domain\Content\Models\HomepageSection;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The homepage's "What's Hot" promo boxes, and the subheading that used to go nowhere.
 *
 * Two things Stefan found by using the screen: a Subheading field that saved a value no section
 * ever displayed, and four promo boxes that named departments while every one of them linked to
 * /shop.
 */
final class WhatsHotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);

        HomepageSection::create([
            'section_type' => 'whats_hot',
            'heading' => "What's Hot",
            'position' => 0,
            'is_visible' => true,
        ]);
    }

    public function test_a_saved_subheading_actually_appears_on_the_homepage(): void
    {
        $section = HomepageSection::query()->where('section_type', 'whats_hot')->firstOrFail();

        /*
         | THE BUG HE REPORTED. The editor's Subheading field wrote to the database and NO section
         | rendered it — not one — so typing a subheading looked exactly like a save that had
         | silently failed.
        */
        $this->actingAs($this->admin())
            ->put(route('admin.homepage.update', $section->id), [
                'heading' => "What's Hot",
                'subheading' => 'Deals of a lifetime',
                'is_visible' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('Deals of a lifetime', $section->fresh()->subheading);

        $this->get('/')->assertOk()->assertSee('Deals of a lifetime', false);
    }

    public function test_clearing_the_subheading_removes_it_from_the_page(): void
    {
        $section = HomepageSection::query()->where('section_type', 'whats_hot')->firstOrFail();
        $section->update(['subheading' => 'Deals of a lifetime']);

        $this->actingAs($this->admin())
            ->put(route('admin.homepage.update', $section->id), [
                'heading' => "What's Hot",
                'subheading' => '',
                'is_visible' => '1',
            ])
            ->assertRedirect();

        // No empty <p> left behind: the section renders it only when there is one.
        $this->get('/')->assertOk()->assertDontSee('Deals of a lifetime', false);
    }

    public function test_a_box_links_to_a_real_category_rather_than_a_typed_url(): void
    {
        $category = Category::query()->where('slug', 'braking')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.homepage.whats-hot.store'), [
                'category_id' => $category->id,
                'headline' => "Brake\nPads",
                'tagline' => 'Stop shorter',
            ])
            ->assertRedirect(route('admin.homepage.index'))
            ->assertSessionHas('status');

        $box = Banner::query()->where('placement', SaveWhatsHotBoxAction::PLACEMENT)->sole();

        /*
         | Generated from the category, never typed. All four shipped boxes pointed at /shop
         | whatever they said — the "Alloy Wheels" box did not go to wheels — and a free-text URL
         | field is how a homepage ends up advertising a department that does not exist.
        */
        $this->assertSame('/shop/braking', $box->link_url);
        $this->assertSame('Shop Now', $box->link_label);

        // And the link works, which is the whole point of choosing from a list.
        $this->get($box->link_url)->assertOk();
    }

    public function test_a_box_with_no_image_falls_back_to_the_themes_placeholder(): void
    {
        $this->addBox();

        $box = Banner::query()->sole();

        // image_path is NOT NULL and the view prefixes a slash, so "no image" has to be a real
        // path. The theme's grey placeholder is honest; an invented photograph would not be.
        $this->assertStringStartsWith('assets/', $box->image_path);
        $this->assertStringContainsString('grey placeholder', (string) session('status'));
    }

    public function test_a_box_image_is_fetched_and_kept_rather_than_linked(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $this->addBox(['image_url' => 'https://93.184.216.34/promo.jpg']);

        $box = Banner::query()->sole();

        $this->assertStringStartsWith('storage/whats-hot/', $box->image_path);
        Storage::disk('public')->assertExists(substr($box->image_path, strlen('storage/')));
        $this->assertSame('https://93.184.216.34/promo.jpg', $box->source_url);
    }

    public function test_a_box_can_be_hidden_and_shown_without_deleting_it(): void
    {
        $this->addBox();
        $box = Banner::query()->sole();

        $this->actingAs($this->admin())
            ->put(route('admin.homepage.whats-hot.update', $box->id), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($box->fresh()->is_active);
        $this->get('/')->assertOk()->assertDontSee('Stop shorter', false);

        $this->actingAs($this->admin())
            ->put(route('admin.homepage.whats-hot.update', $box->id), ['is_active' => '1'])
            ->assertRedirect();

        // Hiding is the safe control, and the one that has to be reversible.
        $this->assertTrue($box->fresh()->is_active);
        $this->get('/')->assertOk()->assertSee('Stop shorter', false);
    }

    public function test_editing_one_control_does_not_disturb_the_others(): void
    {
        $this->addBox();
        $box = Banner::query()->sole();

        $this->actingAs($this->admin())
            ->put(route('admin.homepage.whats-hot.update', $box->id), ['is_active' => '0'])
            ->assertRedirect();

        $box->refresh();

        // A hide click must not blank the headline or the link, which is what happens when every
        // button posts the whole form.
        $this->assertSame("Brake\nPads", $box->title);
        $this->assertSame('/shop/braking', $box->link_url);
    }

    public function test_deleting_a_box_renumbers_the_rest_and_removes_only_its_own_file(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $this->addBox(['image_url' => 'https://93.184.216.34/one.jpg', 'headline' => 'One']);
        $this->addBox(['image_url' => 'https://93.184.216.34/two.jpg', 'headline' => 'Two']);
        $this->addBox(['headline' => 'Three']);   // no image: uses the theme asset

        $first = Banner::query()->where('title', 'One')->sole();
        $firstFile = substr($first->image_path, strlen('storage/'));

        $this->actingAs($this->admin())
            ->delete(route('admin.homepage.whats-hot.destroy', $first->id))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($firstFile);
        // The other box's file is untouched.
        $this->assertCount(1, Storage::disk('public')->files('whats-hot'));

        $this->assertSame([0, 1], Banner::query()
            ->where('placement', SaveWhatsHotBoxAction::PLACEMENT)
            ->orderBy('position')->pluck('position')->all());
    }

    public function test_a_box_can_be_reordered(): void
    {
        $this->addBox(['headline' => 'First']);
        $this->addBox(['headline' => 'Second']);

        $second = Banner::query()->where('title', 'Second')->sole();

        $this->actingAs($this->admin())
            ->put(route('admin.homepage.whats-hot.update', $second->id), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(['Second', 'First'], Banner::query()
            ->where('placement', SaveWhatsHotBoxAction::PLACEMENT)
            ->orderBy('position')->pluck('title')->all());
    }

    public function test_a_category_that_does_not_exist_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.homepage.whats-hot.store'), [
                'category_id' => 'not-a-real-id',
                'headline' => 'Nowhere',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Banner::query()->where('placement', SaveWhatsHotBoxAction::PLACEMENT)->count());
    }

    public function test_a_switched_off_category_cannot_be_chosen(): void
    {
        $category = Category::query()->where('slug', 'braking')->firstOrFail();
        $category->update(['is_active' => false]);

        // Passes the exists rule but must still be refused: a box pointing at a department the
        // shop has switched off is the same broken promise as one pointing nowhere.
        $this->actingAs($this->admin())
            ->post(route('admin.homepage.whats-hot.store'), [
                'category_id' => $category->id,
                'headline' => 'Hidden department',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Banner::query()->where('placement', SaveWhatsHotBoxAction::PLACEMENT)->count());
    }

    public function test_the_route_cannot_be_used_on_a_hero_image(): void
    {
        $hero = Banner::create([
            'placement' => 'home_hero',
            'image_path' => 'storage/hero/x.jpg',
            'position' => 0,
            'is_active' => true,
        ]);

        // Scoped to the placement, so guessing an id cannot delete the homepage's hero picture.
        $this->actingAs($this->admin())
            ->delete(route('admin.homepage.whats-hot.destroy', $hero->id))
            ->assertNotFound();

        $this->assertSame(1, Banner::query()->where('placement', 'home_hero')->count());
    }

    public function test_a_guest_cannot_touch_the_boxes(): void
    {
        $this->addBox();
        $box = Banner::query()->sole();

        /*
         | Logged out explicitly. addBox() authenticates as an admin and actingAs persists for the
         | rest of the test, so without this the "guest" requests would still be the admin and the
         | test would pass while proving nothing.
        */
        Auth::logout();

        $this->post(route('admin.homepage.whats-hot.store'), [])->assertRedirect('/admin/login');
        $this->put(route('admin.homepage.whats-hot.update', $box->id), ['is_active' => '0'])
            ->assertRedirect('/admin/login');
        $this->delete(route('admin.homepage.whats-hot.destroy', $box->id))->assertRedirect('/admin/login');

        $this->assertTrue($box->fresh()->is_active);
    }

    /** @param  array<string, string>  $overrides */
    private function addBox(array $overrides = []): void
    {
        $category = Category::query()->where('slug', 'braking')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.homepage.whats-hot.store'), [
                'category_id' => $category->id,
                'headline' => "Brake\nPads",
                'tagline' => 'Stop shorter',
                ...$overrides,
            ])
            ->assertSessionMissing('error');
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(369, 450);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
