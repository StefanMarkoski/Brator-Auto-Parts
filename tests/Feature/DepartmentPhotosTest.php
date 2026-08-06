<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Actions\AssignDepartmentPhotosAction;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bulk product photos, one set per department.
 *
 * The theme ships no product photography — its "product" files are 206x206 at 1kB and its four
 * detail-page images are byte-identical, so every seeded product shows a grey square. Nobody is
 * uploading 5,000 photographs by hand.
 *
 * THE TEST THAT MATTERS IS THE PROTECTION ONE. The whole point of the design is that staff can
 * give a handful of products real, specific pictures and a later bulk run must not flatten them
 * back to the department's generic photo.
 */
final class DepartmentPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://93.184.216.34';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    public function test_assigning_a_photo_reaches_every_product_in_the_department(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $department = $this->department();
        $expected = $this->productIdsIn($department);

        $this->assertNotEmpty($expected, 'The seed has no products in this department.');

        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.store', $department->id), ['urls' => self::HOST.'/disc.jpg'])
            ->assertRedirect(route('admin.product-photos.index'))
            ->assertSessionHas('status');

        $withPhoto = DB::table('product_images')
            ->whereIn('product_id', $expected)
            ->where('path', 'like', 'storage/departments/%')
            ->distinct()
            ->pluck('product_id')
            ->all();

        $this->assertCount(count($expected), $withPhoto);

        // Fetched ONCE for the whole department, not once per product. 5,000 downloads of the
        // same file is the version of this that never finishes.
        Http::assertSentCount(1);
        $this->assertCount(1, Storage::disk('public')->files('departments'));
    }

    public function test_one_click_can_photograph_the_whole_catalogue(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $expected = Product::query()->count();
        $this->assertGreaterThan(0, $expected);

        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.all'), ['urls' => self::HOST.'/part.jpg'])
            ->assertRedirect(route('admin.product-photos.index'))
            ->assertSessionHas('status');

        // Not one product left showing a placeholder, including any filed under no department at
        // all — that lone grey square is exactly what gets noticed on a demo.
        $withoutPhoto = DB::table('products as p')
            ->whereNull('p.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('product_images as i')
                ->whereColumn('i.product_id', 'p.id')
                ->where('i.path', 'like', 'storage/departments/%'))
            ->count();

        $this->assertSame(0, $withoutPhoto);

        // Fetched ONCE for the whole catalogue, not once per department: eight identical
        // downloads of the same file is the obvious way to write this and eight times the wait.
        Http::assertSentCount(1);
    }

    public function test_the_whole_catalogue_run_also_spares_a_products_own_photograph(): void
    {
        $product = Product::query()->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product->id), [
            'images' => [UploadedFile::fake()->image('real-part.jpg', 900, 700)],
        ])->assertRedirect();

        $own = $product->images()->firstOrFail();

        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.all'), ['urls' => self::HOST.'/part.jpg'])
            ->assertRedirect();

        // The protection is in the shared path, so it holds for the whole-catalogue button too —
        // which is the one most likely to be pressed after somebody has set up a few products.
        $this->assertSame([$own->id], $product->images()->pluck('id')->all());
        $this->assertStringContainsString('left alone', (string) session('status'));
    }

    public function test_the_bulk_photo_becomes_the_card_image_and_replaces_the_placeholder(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $department = $this->department();
        $product = Product::query()->findOrFail($this->productIdsIn($department)[0]);

        // Every seeded product starts with the theme's grey square as its primary.
        $this->assertTrue($product->images()->where('path', 'like', 'assets/%')->exists());

        $this->assignTo($department, self::HOST.'/disc.jpg');

        $images = $product->images()->orderBy('position')->get();

        $this->assertCount(1, $images);
        $this->assertStringStartsWith('storage/departments/', $images[0]->path);
        $this->assertSame(0, $images[0]->position);
        $this->assertTrue($images[0]->is_primary);
        // The alt text is the product's name, not the file's ULID: an empty or meaningless alt on
        // a shop image is an accessibility and an SEO problem.
        $this->assertSame($product->name, $images[0]->alt);
    }

    public function test_a_product_with_its_own_photograph_is_left_alone(): void
    {
        $department = $this->department();
        $product = Product::query()->findOrFail($this->productIdsIn($department)[0]);

        // A real photograph, uploaded on the product's own screen.
        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product->id), [
            'images' => [UploadedFile::fake()->image('real-part.jpg', 900, 700)],
        ])->assertRedirect();

        $own = $product->images()->where('path', 'like', 'storage/products/%')->firstOrFail();

        Http::fake(['*' => Http::response($this->jpeg(), 200)]);
        $this->assignTo($department, self::HOST.'/generic.jpg');

        /*
         | THE POINT OF THE WHOLE FEATURE. Staff give two or three products real pictures for a
         | demo; a bulk run afterwards must not flatten them back to the department's generic one.
        */
        $this->assertSame(
            [$own->id],
            $product->images()->pluck('id')->all(),
            'The bulk assignment overwrote a photograph somebody uploaded.'
        );

        $this->assertStringContainsString('left alone', (string) session('status'));
    }

    public function test_uploading_a_real_photograph_clears_the_placeholder_it_replaces(): void
    {
        $product = Product::query()->firstOrFail();

        $this->assertTrue($product->images()->where('is_primary', true)
            ->where('path', 'like', 'assets/%')->exists());

        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product->id), [
            'images' => [UploadedFile::fake()->image('real-part.jpg', 900, 700)],
        ])->assertRedirect();

        /*
         | Without this the upload landed at position 1, BEHIND the grey square that was still
         | primary — so the card and the product page both kept showing the placeholder for a
         | product that had a real picture, until somebody also clicked "Make main".
        */
        $images = $product->images()->orderBy('position')->get();

        $this->assertCount(1, $images);
        $this->assertStringStartsWith('storage/products/', $images[0]->path);
        $this->assertSame(0, $images[0]->position);
        $this->assertTrue($images[0]->is_primary);
    }

    public function test_a_second_upload_keeps_the_first_and_does_not_steal_the_main_slot(): void
    {
        $product = Product::query()->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product->id), [
            'images' => [UploadedFile::fake()->image('first.jpg', 900, 700)],
        ])->assertRedirect();

        $first = $product->images()->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product->id), [
            'images' => [UploadedFile::fake()->image('second.jpg', 900, 700)],
        ])->assertRedirect();

        // Only placeholders are cleared out. A photograph already uploaded is a human's choice.
        $this->assertSame(2, $product->images()->count());
        $this->assertSame($first->id, $product->images()->where('is_primary', true)->value('id'));
    }

    public function test_reassigning_a_department_replaces_its_previous_photo(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $department = $this->department();
        $this->assignTo($department, self::HOST.'/first.jpg');

        $before = DB::table('product_images')->where('path', 'like', 'storage/departments/%')
            ->distinct()->pluck('path')->all();

        $this->assignTo($department, self::HOST.'/second.jpg');

        $after = DB::table('product_images')->where('path', 'like', 'storage/departments/%')
            ->distinct()->pluck('path')->all();

        $this->assertCount(1, $after);
        $this->assertNotSame($before, $after);

        // And the orphaned file is gone: "a handful per run" is how a disk fills up over a year.
        $this->assertCount(1, Storage::disk('public')->files('departments'));
    }

    public function test_more_photos_than_the_product_page_can_show_are_refused(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $urls = collect(range(1, AssignDepartmentPhotosAction::MAX_PHOTOS + 1))
            ->map(fn (int $n): string => self::HOST."/photo-{$n}.jpg")
            ->implode("\n");

        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.store', $this->department()->id), ['urls' => $urls])
            ->assertSessionHas('error');

        // Refused before anything is fetched — four is all the page has slots for.
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('product_images')->where('path', 'like', 'storage/departments/%')->count());
    }

    public function test_a_bad_url_leaves_the_department_exactly_as_it_was(): void
    {
        /*
         | BOTH stubs registered up front, keyed by URL. Calling Http::fake() a second time
         | APPENDS a stub rather than replacing the first, and the earliest match wins — so a
         | later fake('*') is simply ignored and the test silently exercises the wrong response.
         | That cost a failing run to notice.
        */
        Http::fake([
            '*good.jpg' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
            // An HTML block page behind a .jpg URL — the realistic failure.
            '*bad.jpg' => Http::response('<html>no</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $department = $this->department();
        $this->assignTo($department, self::HOST.'/good.jpg');

        $existing = DB::table('product_images')->where('path', 'like', 'storage/departments/%')->count();

        $this->assertGreaterThan(0, $existing, 'The first assignment stored nothing to protect.');

        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.store', $department->id), ['urls' => self::HOST.'/bad.jpg'])
            ->assertSessionHas('error');

        /*
         | Fetched before anything is deleted, so a bad URL leaves the department with the photos
         | it had — rather than stripped of them and given nothing.
        */
        $this->assertSame($existing, DB::table('product_images')
            ->where('path', 'like', 'storage/departments/%')->count());
    }

    public function test_a_url_inside_our_own_network_is_refused(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.store', $this->department()->id), [
                'urls' => 'http://169.254.169.254/latest/meta-data/photo.jpg',
            ])
            ->assertSessionHas('error');

        // This screen makes the server open a URL a form supplied, so it is the same
        // request-forgery surface as the hero importer and shares its guard.
        Http::assertNothingSent();
    }

    public function test_clearing_a_department_leaves_products_with_no_image_rather_than_a_placeholder(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(), 200)]);

        $department = $this->department();
        $this->assignTo($department, self::HOST.'/disc.jpg');

        $this->actingAs($this->admin())
            ->delete(route('admin.product-photos.destroy', $department->id))
            ->assertRedirect();

        $ids = $this->productIdsIn($department);

        /*
         | No rows at all, not the grey square put back. The views already fall back when a
         | product has no image, so storing a placeholder row would be writing something
         | meaningless into the database to mean "nothing".
        */
        $this->assertSame(0, DB::table('product_images')->whereIn('product_id', $ids)->count());
        $this->assertSame([], Storage::disk('public')->files('departments'));
    }

    public function test_a_guest_cannot_assign_or_clear(): void
    {
        $department = $this->department();

        $this->post(route('admin.product-photos.store', $department->id), ['urls' => self::HOST.'/x.jpg'])
            ->assertRedirect('/admin/login');
        $this->delete(route('admin.product-photos.destroy', $department->id))
            ->assertRedirect('/admin/login');
        $this->get(route('admin.product-photos.index'))->assertRedirect('/admin/login');
    }

    private function assignTo(Category $department, string $url): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product-photos.store', $department->id), ['urls' => $url])
            ->assertSessionMissing('error');
    }

    /** A top-level department that actually has products under it. */
    private function department(): Category
    {
        foreach (Category::query()->where('depth', 0)->orderBy('position')->get() as $candidate) {
            if ($this->productIdsIn($candidate) !== []) {
                return $candidate;
            }
        }

        $this->fail('No department in the seed has any products.');
    }

    /**
     * By PATH, because parts are filed against sub-categories — counting the department's own
     * pivot rows reports zero, a mistake already made twice in this project.
     *
     * @return list<string>
     */
    private function productIdsIn(Category $department): array
    {
        return DB::table('products as p')
            ->join('product_categories as pc', 'pc.product_id', '=', 'p.id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $department->path.'%')
            ->whereNull('p.deleted_at')
            ->distinct()
            ->pluck('p.id')
            ->all();
    }

    /** A real JPEG, so the byte-level format check passes the way it does in production. */
    private function jpeg(): string
    {
        $image = imagecreatetruecolor(900, 700);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
