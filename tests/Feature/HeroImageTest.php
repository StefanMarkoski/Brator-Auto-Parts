<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Content\Actions\ImportHeroImageAction;
use App\Domain\Content\Models\Banner;
use App\Domain\Content\Models\HomepageSection;
use App\Models\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The homepage hero's pictures.
 *
 * Two things are worth testing here and they are not the same thing. One is the behaviour staff
 * see: paste a URL, get a picture; add a second, the banner starts rotating. The other is that
 * an endpoint which makes the SERVER open a URL of somebody else's choosing cannot be turned
 * into a way to read our own network — the tests for that are the ones that matter even though
 * nobody will ever click them.
 *
 * Hosts are written as IP literals throughout so the guard takes its no-DNS path and the suite
 * never depends on name resolution.
 */
final class HeroImageTest extends TestCase
{
    use RefreshDatabase;

    /** A public address, so the guard lets it through. */
    private const PUBLIC_HOST = 'https://93.184.216.34';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // The hero section has to exist for the homepage to render it at all.
        HomepageSection::create([
            'section_type' => 'hero_banner',
            'position' => 0,
            'is_visible' => true,
        ]);
    }

    public function test_with_no_pictures_the_banner_falls_back_to_the_themes_own_background(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // An empty hero would be a hole in the purchased design, so there is always something.
        $this->assertStringContainsString('/assets/images/banner/banner-1.jpg', $html);
        $this->assertStringNotContainsString('data-hero-rotate', $html);
        $this->assertStringNotContainsString('data-hero-page', $html);
    }

    public function test_one_picture_is_shown_without_rotating(): void
    {
        $this->heroImage('storage/hero/only.jpg');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-bg="/storage/hero/only.jpg"', $html);
        // No timer and no dots for a single picture: a rotation of one is a still image
        // with a pointless interval running, and one dot to click is furniture.
        $this->assertStringNotContainsString('data-hero-rotate', $html);
        $this->assertStringNotContainsString('data-hero-page', $html);
    }

    public function test_two_or_more_pictures_rotate_and_get_one_dot_each(): void
    {
        $this->heroImage('storage/hero/one.jpg', 0);
        $this->heroImage('storage/hero/two.webp', 1);
        $this->heroImage('storage/hero/three.jpg', 2);

        $html = $this->get('/')->assertOk()->getContent();

        // The first is painted immediately; all three are handed to the rotator.
        $this->assertStringContainsString('data-bg="/storage/hero/one.jpg"', $html);
        $this->assertStringContainsString('data-hero-interval="5000"', $html);

        foreach (['one.jpg', 'two.webp', 'three.jpg'] as $file) {
            $this->assertStringContainsString(
                str_replace('/', '\/', '/storage/hero/'.$file),
                $html,
                "{$file} is missing from the rotation list."
            );
        }

        // One dot per picture, rendered server-side, and exactly one marked active.
        $this->assertSame(3, substr_count($html, 'data-hero-page='));
        $this->assertSame(1, substr_count($html, 'splide__pagination__page is-active'));
    }

    public function test_the_fade_is_declared_and_is_shorter_than_the_interval(): void
    {
        $this->heroImage('storage/hero/one.jpg', 0);
        $this->heroImage('storage/hero/two.jpg', 1);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/data-hero-fade="(\d+)"/', $html, $fade);
        preg_match('/data-hero-interval="(\d+)"/', $html, $interval);

        $this->assertNotEmpty($fade, 'The banner does not declare a fade duration.');

        /*
         | A fade as long as the interval would leave the hero permanently half-way between
         | two pictures — never quite showing either. The gap is the point: a picture should
         | be fully itself for most of its turn.
        */
        $this->assertLessThan(
            (int) $interval[1],
            (int) $fade[1],
            'The cross-fade must finish well before the next picture is due.'
        );
    }

    public function test_a_single_picture_declares_no_fade(): void
    {
        $this->heroImage('storage/hero/only.jpg');

        // Nothing to fade between, so no timer and no layer. The rotator bails out before
        // building anything, which is why the attribute has to be absent rather than zero.
        $this->get('/')->assertOk()->assertDontSee('data-hero-fade', false);
    }

    public function test_the_dots_reuse_the_themes_own_pagination_classes(): void
    {
        $this->heroImage('storage/hero/one.jpg', 0);
        $this->heroImage('storage/hero/two.jpg', 1);

        // Named explicitly, because the whole reason the hero is a swapped background rather
        // than a slider is to avoid inventing CSS. If somebody later replaces these with a
        // bespoke class, ThemeFidelityTest fails and this says why.
        $this->get('/')->assertOk()
            ->assertSee('class="splide__pagination"', false)
            ->assertSee('splide__pagination__page', false);
    }

    public function test_the_dots_sit_above_the_search_box_as_bare_dots(): void
    {
        $this->heroImage('storage/hero/one.jpg', 0);
        $this->heroImage('storage/hero/two.jpg', 1);

        $html = $this->get('/')->assertOk()->getContent();

        $dots = strpos($html, 'data-hero-pagination');
        $form = strpos($html, 'data-vehicle-picker');

        /*
         | ABOVE THE FILTER, and in the flow. They were an overlay pinned to the bottom of the
         | banner — Splide's own positioning — which put the row on top of the search box.
        */
        $this->assertNotFalse($dots, 'The hero dots are gone.');
        $this->assertLessThan($form, $dots, 'The hero dots render below the vehicle picker.');
        $this->assertStringContainsString('position: static', substr($html, $dots, 200),
            'The dots are absolutely positioned again, so they will float over the search box.');

        /*
         | And bare. The theme's CSS gives every <li> in a pagination list a 24px grey tile with
         | rounded ends, which is where the grey pill came from — it is the list, not the dots.
        */
        preg_match_all('/<li style="([^"]*)"/', substr($html, $dots, 1200), $items);

        $this->assertCount(2, $items[1], 'Expected one list item per picture.');

        foreach ($items[1] as $style) {
            $this->assertStringContainsString('background: none', $style,
                'A dot has its grey tile back.');
        }
    }

    public function test_the_cross_fade_layer_is_sized_by_the_banner_and_not_the_page(): void
    {
        $js = (string) file_get_contents(public_path('app/storefront.js'));

        $positioned = strpos($js, "banner.style.position = 'relative'");
        $inserted = strpos($js, 'banner.insertBefore(layer');

        /*
         | THE ONE THAT GOT AWAY, and why it is asserted here rather than left to a click.
         |
         | The fade layer is position: absolute with all four offsets at zero, so it fills its
         | nearest POSITIONED ancestor. The banner carried an inline position: relative for as
         | long as the dots were an overlay pinned to it; moving the dots into the flow removed
         | it, and the layer quietly started sizing itself against the page — every switch threw
         | the incoming picture across the whole screen and then snapped it back into the banner.
         |
         | Nothing in the markup looked wrong, both changes were correct on their own, and no
         | test could see it: the defect lives entirely in which element is the containing block.
         | So the rotator now guarantees it itself, next to the code that needs it.
        */
        $this->assertNotFalse($positioned,
            'The hero rotator no longer makes the banner a containing block, so its fade layer will fill the page.');
        $this->assertNotFalse($inserted, 'The hero rotator no longer inserts a fade layer.');
        $this->assertLessThan($inserted, $positioned,
            'The banner must be positioned before the fade layer is inserted into it.');
    }

    public function test_a_switched_off_picture_is_not_shown(): void
    {
        $this->heroImage('storage/hero/live.jpg', 0);
        $this->heroImage('storage/hero/hidden.jpg', 1)->update(['is_active' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('live.jpg', $html);
        $this->assertStringNotContainsString('hidden.jpg', $html);
        // Back to one picture, so no rotation.
        $this->assertStringNotContainsString('data-hero-rotate', $html);
    }

    public function test_adding_a_url_stores_the_file_and_records_what_it_got(): void
    {
        Http::fake([
            '*' => Http::response($this->jpeg(1_600, 686), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->addUrl(self::PUBLIC_HOST.'/car.jpg')
            ->assertRedirect(route('admin.homepage.index'))
            ->assertSessionHas('status');

        $banner = Banner::query()->where('placement', 'home_hero')->sole();

        $this->assertSame('1600 × 686', $banner->dimensions());
        $this->assertSame(self::PUBLIC_HOST.'/car.jpg', $banner->source_url);
        $this->assertTrue($banner->is_active);

        // The path is stored ORIGIN-RELATIVE, with no scheme and no host, so the shop serves a
        // working background whatever hostname it is reached on.
        $this->assertStringStartsWith('storage/hero/', $banner->image_path);
        $this->assertStringNotContainsString('://', $banner->image_path);

        // And the bytes are really on our disk — the point of fetching rather than hot-linking.
        Storage::disk('public')->assertExists(substr($banner->image_path, strlen('storage/')));
    }

    public function test_a_webp_is_accepted_and_kept_as_webp(): void
    {
        Http::fake([
            '*' => Http::response($this->webp(), 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->addUrl(self::PUBLIC_HOST.'/car.webp')->assertSessionHas('status');

        $banner = Banner::query()->sole();

        // Kept in its own format rather than re-encoded. Every engine we serve decodes WebP,
        // and because the file is ours the Content-Type is ours to get right.
        $this->assertStringEndsWith('.webp', $banner->image_path);
        Storage::disk('public')->assertExists(substr($banner->image_path, strlen('storage/')));
    }

    public function test_the_extension_comes_from_the_bytes_not_the_url(): void
    {
        // A URL that claims .jpg but serves a PNG. Trusting the URL would store a file whose
        // extension lies about its contents.
        Http::fake([
            '*' => Http::response($this->png(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->addUrl(self::PUBLIC_HOST.'/actually-a-png.jpg')->assertSessionHas('status');

        $this->assertStringEndsWith('.png', Banner::query()->sole()->image_path);
    }

    public function test_a_small_picture_is_accepted_but_says_it_will_look_soft(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(561, 356), 200)]);

        $this->addUrl(self::PUBLIC_HOST.'/thumbnail.jpg')->assertSessionHas('status');

        $banner = Banner::query()->sole();

        // Stored, because an operator may only be able to get a picture small and that is
        // their call — but never silently. "Why is the homepage blurry" should not be a
        // mystery discovered after the fact.
        $this->assertFalse($banner->isComfortableForHero());
        $this->assertStringContainsString('may look soft', (string) session('status'));
    }

    public function test_a_big_enough_picture_says_nothing_about_softness(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(1_920, 700), 200)]);

        $this->addUrl(self::PUBLIC_HOST.'/wide.jpg');

        $this->assertTrue(Banner::query()->sole()->isComfortableForHero());
        $this->assertStringNotContainsString('may look soft', (string) session('status'));
    }

    public function test_a_url_that_serves_html_is_refused_and_nothing_is_stored(): void
    {
        /*
         | The realistic failure. A URL ending in .jpg that answers with a block page is what
         | one of the hosts this was built against does to requests it dislikes, and storing
         | that would put a broken background on the homepage with nothing to explain it.
        */
        Http::fake([
            '*' => Http::response('<html><body>Forbidden</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->addUrl(self::PUBLIC_HOST.'/blocked.jpg')->assertSessionHas('error');

        $this->assertSame(0, Banner::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_url_that_answers_404_is_refused(): void
    {
        Http::fake(['*' => Http::response('nope', 404)]);

        $this->addUrl(self::PUBLIC_HOST.'/missing.jpg')->assertSessionHas('error');

        $this->assertSame(0, Banner::query()->count());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function internalUrlProvider(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/x.jpg'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/x.jpg'],
            'private class A' => ['http://10.0.0.5/x.jpg'],
            'private class B' => ['http://172.16.4.4/x.jpg'],
            'private class C' => ['http://192.168.1.1/x.jpg'],
        ];
    }

    #[DataProvider('internalUrlProvider')]
    public function test_a_url_inside_our_own_network_is_refused(string $url): void
    {
        Http::fake(['*' => Http::response($this->jpeg(1_600, 686), 200)]);

        $this->addUrl($url)->assertSessionHas('error');

        /*
         | The whole point. This endpoint makes the server open a URL a form supplied, which
         | unguarded is a request-forgery hole: the database port, the Redis port, or the cloud
         | metadata address would be fetched from inside our network and handed back through the
         | admin. Refused before any request is made, so nothing is even probed.
        */
        $this->assertSame(0, Banner::query()->count());
        Http::assertNothingSent();
    }

    public function test_a_non_http_scheme_is_refused(): void
    {
        // file:// is how a fetcher like this gets turned into a way to read our own disk.
        // Caught by validation before the action ever sees it.
        $this->actingAs($this->admin())
            ->post(route('admin.homepage.hero-images.store'), ['url' => 'file:///etc/passwd'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, Banner::query()->count());
    }

    public function test_a_redirect_is_followed_but_only_to_a_public_address(): void
    {
        Http::fake([
            '*/start.jpg' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.jpg']),
        ]);

        $this->addUrl(self::PUBLIC_HOST.'/start.jpg')->assertSessionHas('error');

        // A redirect is the obvious way around a guard that only checks the URL it was given,
        // so each hop is checked on its own.
        $this->assertSame(0, Banner::query()->count());
    }

    public function test_pictures_are_appended_in_the_order_they_were_added(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(1_600, 686), 200)]);

        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $file) {
            $this->addUrl(self::PUBLIC_HOST.'/'.$file);
        }

        // Appended rather than inserted, so adding a picture never reshuffles a rotation
        // somebody has already arranged.
        $this->assertSame([0, 1, 2], Banner::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_removing_a_picture_deletes_the_file_and_closes_the_gap(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(1_600, 686), 200)]);

        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $file) {
            $this->addUrl(self::PUBLIC_HOST.'/'.$file);
        }

        $middle = Banner::query()->orderBy('position')->skip(1)->first();
        $path = substr($middle->image_path, strlen('storage/'));

        $this->actingAs($this->admin())
            ->delete(route('admin.homepage.hero-images.destroy', $middle->id))
            ->assertRedirect(route('admin.homepage.index'));

        Storage::disk('public')->assertMissing($path);
        // Renumbered densely, so "position" keeps meaning "nth in the rotation" and the dots
        // stay in step with the pictures.
        $this->assertSame([0, 1], Banner::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_removing_a_seeded_picture_does_not_delete_the_themes_file(): void
    {
        $themeAsset = $this->heroImage('assets/images/slider/slider-01.jpg');

        $this->actingAs($this->admin())
            ->delete(route('admin.homepage.hero-images.destroy', $themeAsset->id))
            ->assertRedirect();

        $this->assertSame(0, Banner::query()->count());
        /*
         | Only files WE fetched are deleted from disk. A seeded row points at the purchased
         | theme's own slider asset, which is shared and not ours to remove — deleting one
         | banner must never damage the template.
        */
        $this->assertFileExists(public_path('assets/images/slider/slider-01.jpg'));
    }

    public function test_a_banner_from_another_placement_cannot_be_removed_here(): void
    {
        $other = Banner::create([
            'placement' => 'home_secondary',
            'image_path' => 'storage/hero/not-a-hero.jpg',
            'position' => 0,
            'is_active' => true,
        ]);

        // Scoped to the hero, so this route cannot be used to delete the "What's Hot" promo
        // boxes by guessing an id.
        $this->actingAs($this->admin())
            ->delete(route('admin.homepage.hero-images.destroy', $other->id))
            ->assertNotFound();

        $this->assertSame(1, Banner::query()->count());
    }

    public function test_a_guest_can_neither_add_nor_remove(): void
    {
        $banner = $this->heroImage('storage/hero/one.jpg');

        $this->post(route('admin.homepage.hero-images.store'), ['url' => self::PUBLIC_HOST.'/x.jpg'])
            ->assertRedirect('/admin/login');
        $this->delete(route('admin.homepage.hero-images.destroy', $banner->id))
            ->assertRedirect('/admin/login');

        $this->assertSame(1, Banner::query()->count());
    }

    public function test_the_editor_shows_each_picture_with_its_size_and_source(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(561, 356), 200)]);
        $this->addUrl(self::PUBLIC_HOST.'/thumb.jpg');

        $response = $this->actingAs($this->admin())->get(route('admin.homepage.index'))->assertOk();

        $response->assertSee('561 × 356', false)
            ->assertSee('93.184.216.34', false)
            ->assertSee('will look soft', false);
    }

    public function test_the_editor_says_whether_the_banner_will_rotate(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(1_600, 686), 200)]);

        $this->addUrl(self::PUBLIC_HOST.'/a.jpg');
        $this->actingAs($this->admin())->get(route('admin.homepage.index'))
            ->assertSee('it sits still', false);

        $this->addUrl(self::PUBLIC_HOST.'/b.jpg');
        $this->actingAs($this->admin())->get(route('admin.homepage.index'))
            ->assertSee('rotates every', false);
    }

    public function test_the_action_reports_a_useless_url_in_words_an_operator_can_act_on(): void
    {
        Http::fake(['*' => Http::response('<html>no</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->expectException(RuntimeException::class);
        // Not a stack trace and not "cURL error 7". The person pasting a URL has to be able to
        // tell whether they got the wrong link or the host refused us.
        $this->expectExceptionMessageMatches('/did not return an image/');

        app(ImportHeroImageAction::class)->import(self::PUBLIC_HOST.'/x.jpg');
    }

    public function test_the_fetch_identifies_itself_as_a_browser(): void
    {
        Http::fake(['*' => Http::response($this->jpeg(1_600, 686), 200)]);

        $this->addUrl(self::PUBLIC_HOST.'/car.jpg');

        // Some image hosts answer a default client string with an HTML block page — the sixt
        // URL this was built against does exactly that — so the fetch presents a browser
        // user-agent. If that is ever dropped, those URLs start failing for no visible reason.
        Http::assertSent(fn (Request $request): bool => str_contains(
            $request->header('User-Agent')[0] ?? '',
            'Mozilla/5.0'
        ));
    }

    private function addUrl(string $url): TestResponse
    {
        return $this->actingAs($this->admin())
            ->post(route('admin.homepage.hero-images.store'), ['url' => $url]);
    }

    private function heroImage(string $path, int $position = 0): Banner
    {
        return Banner::create([
            'placement' => 'home_hero',
            'image_path' => $path,
            'position' => $position,
            'is_active' => true,
        ]);
    }

    /** A real JPEG of a known size, so getimagesizefromstring reads real dimensions. */
    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(1_600, 686);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function webp(): string
    {
        $image = imagecreatetruecolor(1_600, 686);
        ob_start();
        imagewebp($image);

        return (string) ob_get_clean();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
