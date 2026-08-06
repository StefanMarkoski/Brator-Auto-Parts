<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Product photographs.
 *
 * The table and the storefront's use of it both existed already; what was missing was any
 * way to write it, so every hand-created product showed the theme's grey placeholder.
 */
final class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
        Storage::fake('public');
    }

    public function test_an_uploaded_image_lands_on_disk_and_becomes_the_main_one(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())
            ->post("/admin/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->image('brake-disc.jpg', 800, 800)],
            ])
            ->assertRedirect(route('admin.products.edit', $product->id));

        $image = $product->images()->firstOrFail();

        // The first image on a product with none must be primary, or the product has
        // photographs while its card still falls back to a placeholder — the card read
        // joins on is_primary.
        $this->assertTrue($image->is_primary);

        // Stored ORIGIN-RELATIVE: no scheme, no host. Storage::url() would bake APP_URL in,
        // and the shop is reached on more than one host.
        $this->assertStringStartsWith('storage/products/', $image->path);
        $this->assertStringNotContainsString('http', $image->path);

        Storage::disk('public')->assertExists(substr($image->path, strlen('storage/')));
    }

    public function test_an_uploaded_image_shows_on_the_product_page(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('front.jpg', 800, 800)],
        ]);

        $path = $product->images()->firstOrFail()->path;

        // The panel and the shop have to agree. Asserting the row was written proves
        // nothing about what a shopper sees.
        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertSee($path, false);
    }

    public function test_several_images_upload_in_order_with_only_one_primary(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ]);

        $images = $product->images()->orderBy('position')->get();

        $this->assertCount(3, $images);
        $this->assertSame(1, $images->where('is_primary', true)->count(),
            'is_primary is not a unique index, so two primaries is a state the database will hold.');
        $this->assertTrue($images->first()->is_primary);
    }

    public function test_making_another_image_primary_demotes_the_previous_one(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ]);

        $second = $product->images()->orderBy('position')->skip(1)->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}/images/{$second->id}", ['action' => 'primary'])
            ->assertRedirect();

        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_deleting_an_uploaded_image_removes_the_file_too(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('gone.jpg')],
        ]);

        $image = $product->images()->firstOrFail();
        $relative = substr($image->path, strlen('storage/'));

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}/images/{$image->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        // Orphaned files accumulate silently and nobody ever notices until the disk fills.
        Storage::disk('public')->assertMissing($relative);
    }

    public function test_deleting_a_seeded_image_leaves_the_theme_file_alone(): void
    {
        $product = Product::query()->firstOrFail();
        $seeded = $product->images()->firstOrFail();

        // Seeded rows point at the purchased theme's own files, shared by thousands of
        // products. Deleting one product's link must not delete the template's asset.
        $this->assertStringStartsWith('assets/', $seeded->path);

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}/images/{$seeded->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('product_images', ['id' => $seeded->id]);
        $this->assertFileExists(public_path($seeded->path));
    }

    public function test_deleting_the_main_image_promotes_the_next_one(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ]);

        $primary = $product->images()->where('is_primary', true)->firstOrFail();

        $this->actingAs($this->admin())->delete("/admin/products/{$product->id}/images/{$primary->id}");

        // Otherwise the product keeps a photograph while its card falls back to a placeholder.
        $this->assertSame(1, $product->images()->where('is_primary', true)->count(),
            'Deleting the main image must promote the next one.');
    }

    public function test_reordering_keeps_positions_dense(): void
    {
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ]);

        $last = $product->images()->orderBy('position')->skip(2)->firstOrFail();

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}/images/{$last->id}", ['action' => 'up']);

        $positions = $product->images()->orderBy('position')->pluck('position')->all();

        $this->assertSame([0, 1, 2], $positions);
        $this->assertSame(1, (int) $last->fresh()->position);
    }

    public function test_a_non_image_upload_is_refused(): void
    {
        $product = Product::query()->firstOrFail();
        $before = $product->images()->count();

        // Checked by contents, not by extension: a .jpg that is really a script would
        // otherwise land in a directory the web server serves.
        $this->actingAs($this->admin())
            ->post("/admin/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->create('payload.jpg', 16, 'application/x-php')],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertSame($before, $product->images()->count());
    }

    public function test_an_image_cannot_be_deleted_through_another_products_route(): void
    {
        $owner = Product::query()->firstOrFail();
        $other = Product::query()->whereKeyNot($owner->id)->firstOrFail();
        $image = $owner->images()->firstOrFail();

        // The lookup is scoped to the product in the URL. Without that, any image id could
        // be deleted through any product's route.
        $this->actingAs($this->admin())
            ->delete("/admin/products/{$other->id}/images/{$image->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_a_guest_cannot_upload_or_delete_images(): void
    {
        $product = Product::query()->firstOrFail();
        $image = $product->images()->firstOrFail();

        $this->post("/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('sneaky.jpg')],
        ])->assertRedirect('/admin/login');

        $this->delete("/admin/products/{$product->id}/images/{$image->id}")->assertRedirect('/admin/login');

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->count());
    }

    public function test_the_product_page_leads_with_the_main_image(): void
    {
        $product = Product::query()->firstOrFail();

        /*
         | BOTH images are uploads, so both paths are unique to this product.
         |
         | The first version of this test compared an upload against the product's SEEDED
         | image — and seeded rows point at the purchased theme's shared asset files, so the
         | same path appears on other products' cards further down the same page. strpos
         | found one of those and the test failed while the code was right. A position
         | assertion needs strings that occur exactly once.
        */
        $product->images()->delete();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.jpg')],
        ]);

        $first = $product->images()->orderBy('position')->firstOrFail();
        $second = $product->images()->orderBy('position')->skip(1)->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}/images/{$second->id}", ['action' => 'primary']);

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        // The cards join on is_primary but this page reads images[0], so before the ordering
        // was fixed the main image chosen in the panel governed every card in the shop and
        // not the product's own page — the one place a shopper looks hardest.
        $this->assertLessThan(
            strpos($html, $first->path),
            strpos($html, $second->path),
            'The product page did not lead with the image marked as main.'
        );
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
