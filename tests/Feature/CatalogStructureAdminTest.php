<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category and brand CRUD.
 *
 * The materialised `path` is the thing these tests exist for. Listing pages resolve a whole
 * department with `path LIKE '/braking/%'` in one indexed query, so a stale path does not
 * produce a slightly wrong page — it produces an empty one.
 */
final class CatalogStructureAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    public function test_a_new_department_gets_a_root_path_and_appears_in_the_shop(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/categories', ['name' => 'Bodywork', 'is_active' => 1])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'bodywork')->firstOrFail();

        $this->assertSame('/bodywork/', $category->path);
        $this->assertSame(0, $category->depth);
        $this->assertNull($category->parent_id);

        $this->get('/shop/bodywork')->assertOk();
    }

    public function test_a_sub_category_nests_under_its_parent(): void
    {
        $parent = Category::query()->where('depth', 0)->firstOrFail();

        $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Wiper Blades',
            'parent_id' => $parent->id,
            'is_active' => 1,
        ]);

        $child = Category::query()->where('slug', 'wiper-blades')->firstOrFail();

        $this->assertSame($parent->path.'wiper-blades/', $child->path);
        $this->assertSame(1, $child->depth);
    }

    public function test_renaming_a_departments_slug_rewrites_every_descendant_path(): void
    {
        $parent = Category::query()->where('depth', 0)->with('children')->firstOrFail();
        $this->assertNotEmpty($parent->children, 'This test needs a department with children.');

        $this->actingAs($this->admin())->put("/admin/categories/{$parent->id}", [
            'name' => $parent->name,
            'slug' => 'renamed-department',
            'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $parent->refresh();
        $this->assertSame('/renamed-department/', $parent->path);

        // The half that is easy to forget. Without it the children still claim to live
        // under the old path, so the department page finds none of them and each
        // sub-category drops out of the filter sidebar.
        foreach ($parent->children()->get() as $child) {
            $this->assertSame("/renamed-department/{$child->slug}/", $child->path,
                'A descendant kept the old path after its parent was renamed.');
            $this->assertSame(1, $child->depth);
        }
    }

    public function test_the_shop_still_finds_a_departments_products_after_a_rename(): void
    {
        $parent = Category::query()->where('depth', 0)->withCount('products')->firstOrFail();

        // Proves the rename is coherent end to end, not just that a string was updated:
        // the listing query filters on the path prefix.
        $before = $this->get("/shop/{$parent->slug}")->assertOk()->getContent();

        $this->actingAs($this->admin())->put("/admin/categories/{$parent->id}", [
            'name' => $parent->name,
            'slug' => 'moved-department',
            'is_active' => 1,
        ]);

        $after = $this->get('/shop/moved-department')->assertOk()->getContent();

        $countOf = static fn (string $html): string => (string) (
            preg_match('/(\d[\d.,]*)\s+(?:results?|parts?)/i', $html, $m) ? $m[1] : ''
        );

        // Guarded against passing vacuously: if the regex matched nothing on either page
        // this assertion would compare '' to '' and prove nothing at all. That exact shape
        // is how four earlier tests in this project passed while testing nothing.
        $this->assertNotSame('', $countOf($before), 'The count was not found on the page.');

        $this->assertSame($countOf($before), $countOf($after),
            'The department lost products when its slug changed.');
    }

    public function test_moving_a_category_into_its_own_subtree_is_refused(): void
    {
        $parent = Category::query()->where('depth', 0)->with('children')->firstOrFail();
        $child = $parent->children->first();

        $this->assertNotNull($child);

        // Allowing this detaches the whole branch: no path involved is reachable from a
        // root any more, so those categories vanish from the shop while looking fine in
        // the table.
        $this->actingAs($this->admin())->put("/admin/categories/{$parent->id}", [
            'name' => $parent->name,
            'parent_id' => $child->id,
            'is_active' => 1,
        ])->assertSessionHas('error');

        $parent->refresh();
        $this->assertNull($parent->parent_id);
        $this->assertSame(0, $parent->depth);
    }

    public function test_deleting_a_category_with_products_is_refused_with_the_count(): void
    {
        $category = Category::query()->has('products')->withCount('products')->firstOrFail();
        $count = $category->products_count;

        $this->actingAs($this->admin())
            ->delete("/admin/categories/{$category->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        // The refusal has to name the number, or "cannot delete" is a dead end with no
        // next step.
        $this->assertStringContainsString((string) $count, (string) session('error'));
    }

    public function test_deleting_a_department_with_sub_categories_is_refused(): void
    {
        $parent = Category::query()->where('depth', 0)->has('children')->firstOrFail();

        // The foreign key is nullOnDelete, so without this check the children would be
        // silently promoted to departments with stale paths — a whole branch of the shop
        // rearranged by one click.
        $this->actingAs($this->admin())
            ->delete("/admin/categories/{$parent->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $this->actingAs($this->admin())->post('/admin/categories', ['name' => 'Temporary', 'is_active' => 1]);
        $category = Category::query()->where('slug', 'temporary')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete("/admin/categories/{$category->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_a_duplicate_category_name_gets_its_own_slug_rather_than_an_error(): void
    {
        $existing = Category::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/categories', ['name' => $existing->name, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        // Two departments can legitimately want the same short name, so the action finds a
        // free slug instead of rejecting the form.
        $this->assertSame(2, Category::query()->where('name', $existing->name)->count());
        $this->assertSame(1, Category::query()->where('slug', $existing->slug.'-2')->count());
    }

    public function test_staff_can_create_edit_and_delete_a_brand(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/brands', ['name' => 'Nissens Cooling', 'is_active' => 1])
            ->assertRedirect(route('admin.brands.index'));

        $brand = Brand::query()->where('slug', 'nissens-cooling')->firstOrFail();

        $this->actingAs($this->admin())->put("/admin/brands/{$brand->id}", [
            'name' => 'Nissens',
            'slug' => 'nissens-cooling',
            'is_active' => 1,
        ]);

        $this->assertSame('Nissens', $brand->fresh()->name);
        // The slug was given explicitly, so it must be respected rather than rebuilt from
        // the new name — every brand link already in the wild uses it.
        $this->assertSame('nissens-cooling', $brand->fresh()->slug);

        $this->actingAs($this->admin())
            ->delete("/admin/brands/{$brand->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_deleting_a_brand_with_products_is_refused(): void
    {
        $brand = Brand::query()->has('products')->withCount('products')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete("/admin/brands/{$brand->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('brands', ['id' => $brand->id]);

        // The foreign key is nullOnDelete: without the refusal these products would stay
        // sellable but lose their maker, and fall out of the brand filter entirely.
        $this->assertSame($brand->products_count,
            Product::query()->where('brand_id', $brand->id)->count());
    }

    public function test_a_guest_cannot_change_the_catalogue_structure(): void
    {
        $category = Category::query()->firstOrFail();
        $brand = Brand::query()->firstOrFail();

        $this->post('/admin/categories', ['name' => 'Sneaky'])->assertRedirect('/admin/login');
        $this->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/login');
        $this->post('/admin/brands', ['name' => 'Sneaky'])->assertRedirect('/admin/login');
        $this->delete("/admin/brands/{$brand->id}")->assertRedirect('/admin/login');

        $this->assertDatabaseMissing('categories', ['name' => 'Sneaky']);
        $this->assertDatabaseMissing('brands', ['name' => 'Sneaky']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
