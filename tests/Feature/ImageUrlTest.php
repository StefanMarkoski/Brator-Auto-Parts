<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ImageUrl;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stored image paths becoming URLs — the one thing standing between this app and any host
 * with an ephemeral filesystem.
 *
 * The rule has two halves and the second is the one that breaks quietly:
 *
 *   storage/…   uploaded or fetched. Follows the uploads disk wherever it goes.
 *   assets/…    the purchased theme's own files, served off public/ exactly as shipped.
 *               NEVER follows the disk. If these ever get rewritten to object storage the
 *               whole shop loses its styling images and its logo, on a bucket that does
 *               not contain them, and every page still returns 200 while looking broken.
 *
 * The local half must also stay ORIGIN-RELATIVE. Storage::url() bakes APP_URL into the
 * value, and this shop is reached on several hosts — localhost, a LAN IP from a phone, a
 * tunnel, a demo domain. A URL carrying the wrong host is how images "disappear and never
 * come back".
 */
final class ImageUrlTest extends TestCase
{
    use RefreshDatabase;

    private function useObjectStorage(): void
    {
        // No credentials and no network: url() only formats a string. That is deliberate —
        // this asserts the URL SHAPE, which is what the views depend on.
        config()->set('filesystems.uploads_disk', 's3');
        config()->set('filesystems.disks.s3.url', 'https://cdn.example.com/brator');
    }

    public function test_a_theme_asset_is_origin_relative(): void
    {
        $this->assertSame('/assets/images/logo.png', ImageUrl::for('assets/images/logo.png'));
        $this->assertSame('/assets/images/logo.png', ImageUrl::for('/assets/images/logo.png'));
    }

    public function test_an_upload_on_the_local_disk_is_origin_relative(): void
    {
        // No scheme, no host — exactly what the views emitted by hand before this existed,
        // so moving to it changed no local output at all.
        $this->assertSame('/storage/products/abc.webp', ImageUrl::for('storage/products/abc.webp'));
    }

    public function test_an_upload_follows_the_disk_to_object_storage(): void
    {
        $this->useObjectStorage();

        // The stored path carries a "storage/" prefix that is NOT part of the object key —
        // the write side strips it too, so getting this wrong puts every image one folder
        // deep in a bucket that does not have that folder.
        $this->assertSame(
            'https://cdn.example.com/brator/products/abc.webp',
            ImageUrl::for('storage/products/abc.webp'),
        );
    }

    public function test_a_theme_asset_does_not_follow_the_disk(): void
    {
        $this->useObjectStorage();

        // The load-bearing one. The theme ships these; they are not in anybody's bucket.
        $this->assertSame('/assets/images/logo.png', ImageUrl::for('assets/images/logo.png'));
        $this->assertSame('/app/images/categories/engine.png', ImageUrl::for('app/images/categories/engine.png'));
    }

    public function test_nothing_becomes_an_empty_string_rather_than_a_broken_url(): void
    {
        // '/' as a src makes the browser re-request the page as an image. Callers all have
        // a placeholder for the no-image case; give them something falsy to test.
        $this->assertSame('', ImageUrl::for(null));
        $this->assertSame('', ImageUrl::for(''));
        $this->assertSame('', ImageUrl::for('   '));
    }

    public function test_a_url_that_is_already_absolute_is_left_alone(): void
    {
        $this->assertSame('https://example.com/a.jpg', ImageUrl::for('https://example.com/a.jpg'));
        $this->assertSame('//example.com/a.jpg', ImageUrl::for('//example.com/a.jpg'));
    }

    public function test_the_write_disk_and_the_read_disk_are_the_same_setting(): void
    {
        // If these ever diverge, files land somewhere real under URLs pointing elsewhere,
        // and every upload appears to succeed while showing a broken image.
        $this->assertSame('public', ImageUrl::disk());

        $this->useObjectStorage();
        $this->assertSame('s3', ImageUrl::disk());
    }

    public function test_a_misconfigured_bucket_breaks_a_picture_and_not_the_shop(): void
    {
        /*
         | The real incident, reproduced. UPLOADS_DISK=s3 was set on Laravel Cloud while
         | AWS_DEFAULT_REGION was not reaching the app. The AWS SDK refuses to build a client
         | without a region, so this threw — once per product card, dozens of times a page —
         | and every page of the shop returned 500. A storage misconfiguration became a total
         | outage, over images.
         |
         | The right severity for "the picture cannot be located" is a broken picture.
        */
        config()->set('filesystems.uploads_disk', 's3');
        config()->set('filesystems.disks.s3.region', null);
        config()->set('filesystems.disks.s3.url', null);

        $url = ImageUrl::for('storage/departments/photo.jpg');

        $this->assertSame('/storage/departments/photo.jpg', $url);
    }

    public function test_the_page_still_renders_when_the_uploads_disk_is_unusable(): void
    {
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);

        config()->set('filesystems.uploads_disk', 's3');
        config()->set('filesystems.disks.s3.region', null);

        // The assertion that actually mattered: 200, not 500.
        $this->get('/')->assertOk();
        $this->get('/shop/braking')->assertOk();
    }

    public function test_the_storefront_still_emits_origin_relative_images_locally(): void
    {
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);

        $html = $this->get('/')->assertOk()->getContent();

        // Every image on the page, ignoring the theme's inline base64 placeholder.
        preg_match_all('/data-(?:src|bg)="([^"]+)"/', $html, $m);
        $real = array_values(array_filter($m[1], fn (string $u): bool => ! str_starts_with($u, 'data:')));

        $this->assertNotEmpty($real, 'No images found on the homepage — this test would pass vacuously.');

        foreach ($real as $url) {
            $this->assertStringStartsWith('/', $url, "Image URL is not origin-relative: {$url}");
            $this->assertStringNotContainsString('://', $url, "Image URL carries a host: {$url}");
        }
    }
}
