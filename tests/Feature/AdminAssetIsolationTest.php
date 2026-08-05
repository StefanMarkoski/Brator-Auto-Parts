<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The single most important guard in the admin panel.
 *
 * TailAdmin is Tailwind, and Tailwind ships a global reset (Preflight) that would
 * flatten the purchased Brator theme anywhere the two met. The separation is
 * structural — separate layouts, separate build, separate route group, nothing shared,
 * not even a head partial — and these tests assert every part of it, because "we'll
 * remember not to mix them" is not a guarantee.
 */
final class AdminAssetIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const STOREFRONT_PAGES = ['/', '/shop', '/cart', '/about', '/contact'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CatalogStructureSeeder::class,
            ProductSeederSmall::class,
            FitmentSeederSmall::class,
            MerchandisingSeeder::class,
            HomepageSeeder::class,
            ContentSeeder::class,
        ]);
    }

    public function test_no_storefront_page_loads_the_admin_bundle(): void
    {
        foreach (self::STOREFRONT_PAGES as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $this->assertStringNotContainsString('/build/assets/admin', $html,
                "{$page} is loading the admin bundle. Tailwind's reset will flatten the theme.");
            $this->assertStringNotContainsString('/build/manifest.json', $html);
        }
    }

    public function test_no_storefront_page_carries_tailwind_utility_classes(): void
    {
        // A handful of unmistakably-Tailwind utilities. The theme uses none of them, so
        // any appearance means admin markup has leaked into a storefront view.
        $tells = ['dark:bg-gray-900', 'rounded-2xl', 'xl:flex', 'text-gray-800', 'bg-brand-500'];

        foreach (self::STOREFRONT_PAGES as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach ($tells as $utility) {
                $this->assertStringNotContainsString($utility, $html,
                    "Tailwind utility '{$utility}' appeared on {$page} — admin markup has leaked in.");
            }
        }
    }

    public function test_the_storefront_still_loads_the_theme_stylesheets_unchanged(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $active = (string) preg_replace('/<!--.*?-->/s', '', $html);

        preg_match_all('#href="(/assets/css/[^"]+)"#', $active, $m);

        $this->assertSame([
            '/assets/css/bootstrap-grid.min.css',
            '/assets/css/splide.min.css',
            '/assets/css/splide-core.min.css',
            '/assets/css/nouislider.css',
            '/assets/css/select2.min.css',
            '/assets/css/theme-style.css',
            '/assets/css/theme-style-home-two.css',
            '/assets/css/url.css',
        ], $m[1], 'Adding the admin panel must not have touched the theme stylesheets.');
    }

    public function test_only_the_admin_layouts_reference_vite(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = str_replace(resource_path('views').'/', '', $file);

            if (str_starts_with($relative, 'admin/')) {
                continue;
            }

            if (str_contains((string) file_get_contents($file), '@vite')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders,
            "These non-admin views reference @vite:\n  ".implode("\n  ", $offenders));
    }

    public function test_no_storefront_view_includes_an_admin_partial(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = str_replace(resource_path('views').'/', '', $file);

            if (str_starts_with($relative, 'admin/') || str_starts_with($relative, 'components/admin/')) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            if (preg_match("/@(include|extends)\(['\"]admin\./", $contents) || str_contains($contents, '<x-admin.')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders,
            "These storefront views pull in admin markup:\n  ".implode("\n  ", $offenders));
    }

    public function test_no_admin_view_includes_a_storefront_partial(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = str_replace(resource_path('views').'/', '', $file);

            if (! str_starts_with($relative, 'admin/')) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            // The reverse leak matters too: the theme's jQuery and Tailwind fighting
            // over one DOM is a whole class of bug worth designing out.
            if (preg_match("/@(include|extends)\(['\"](partials|layouts|shop|home|pages)\./", $contents)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders,
            "These admin views pull in storefront markup:\n  ".implode("\n  ", $offenders));
    }

    public function test_the_vite_config_builds_admin_assets_only(): void
    {
        $config = (string) file_get_contents(base_path('vite.config.js'));

        // Parse the actual input array rather than grepping the file — the first
        // version of this test searched for the word "storefront" and was tripped by
        // the comment explaining why no storefront entry belongs here.
        preg_match('/input:\s*\[(.*?)\]/s', $config, $matches);
        $this->assertNotEmpty($matches, 'Could not find the Vite input array.');

        preg_match_all("/'([^']+)'/", $matches[1], $entries);

        // Every build entry must be an admin asset. A storefront entry here would mean
        // the theme is no longer served byte-identical off disk.
        $this->assertNotEmpty($entries[1]);

        foreach ($entries[1] as $entry) {
            $this->assertMatchesRegularExpression('#^resources/(css|js)/admin\.(css|js)$#', $entry,
                "Vite build entry '{$entry}' is not an admin asset.");
        }
    }

    public function test_tailwind_only_scans_the_admin_views(): void
    {
        $css = (string) file_get_contents(resource_path('css/admin.css'));

        $this->assertStringContainsString("@source '../views/admin/**/*.blade.php';", $css);
        $this->assertStringNotContainsString("@source '../**/*.blade.php';", $css,
            'Tailwind must not scan the theme markup.');
    }

    public function test_the_admin_panel_does_load_its_own_bundle(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        // The other half of the guarantee: isolation must not have broken the panel.
        $this->assertStringContainsString('/build/assets/admin', $html);
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
