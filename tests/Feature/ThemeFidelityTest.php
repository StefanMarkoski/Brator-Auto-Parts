<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the project's hardest rule: no changes to the theme's styling.
 *
 * HOW THIS TEST CHANGED IN PHASE 3, and why. Until the homepage became dynamic, it
 * asserted the rendered markup matched the original template byte for byte. That
 * assertion is meaningless now — real products have different names and prices than
 * the theme's demo content, and the page renders a different number of cards. Keeping
 * it would have meant either freezing the page as static or deleting the guard.
 *
 * So the guarantee is restated as the thing we actually care about: we never introduce
 * a CSS class the theme does not already ship, and we never lose one of its sections.
 * Those two properties are what "the styling is unchanged" really means once content
 * is live — and unlike a byte comparison, they keep holding as the shop fills up.
 */
final class ThemeFidelityTest extends TestCase
{
    use RefreshDatabase;

    /** Every original theme page — the full vocabulary of classes we may use. */
    private const REFERENCE_DIR = 'resources/theme-reference';

    public function test_the_page_introduces_no_css_class_the_theme_does_not_ship(): void
    {
        $this->seedShop();

        $rendered = $this->get('/')->assertOk()->getContent();

        $themeClasses = $this->classesIn($this->allReferenceMarkup());
        $pageClasses = $this->classesIn($rendered);

        $invented = array_values(array_diff($pageClasses, $themeClasses));

        $this->assertSame([], $invented,
            'These CSS classes do not exist anywhere in the purchased theme, which means '
            ."new styling was introduced:\n  ".implode("\n  ", $invented));
    }

    public function test_no_theme_section_has_been_lost_from_the_homepage(): void
    {
        $this->seedShop();

        $rendered = $this->get('/')->assertOk()->getContent();

        // One landmark class per section of the theme's index-2, in render order. If a
        // section stops appearing, a whole block of the design has silently vanished.
        $landmarks = [
            'brator-header-top-bar-area',      // header
            'brator-main-banner-area',         // hero
            'brator-categories-single',        // categories strip
            'brator-hot-single-box',           // what's hot
            'brator-makes-list-single',        // featured makes
            'brator-product-single-item-area', // product strips
            'brator-brand-img',                // featured brands
            'brator-blog-listing-single-item-area', // articles
            'brator-footer-area',              // footer
        ];

        foreach ($landmarks as $landmark) {
            $this->assertStringContainsString($landmark, $rendered,
                "The theme section identified by '{$landmark}' is no longer rendering.");
        }
    }

    public function test_every_asset_the_homepage_references_is_root_relative(): void
    {
        $this->seedShop();

        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/(?:href|src|data-src|data-bg)="((?:\.\/)?assets\/[^"]+)"/', $html, $m);

        $this->assertEmpty($m[1],
            'These asset URLs are page-relative and will 404 on nested routes such as '
            ."/product/{slug}. They must start with a leading slash:\n  "
            .implode("\n  ", array_unique($m[1])));
    }

    public function test_the_theme_stylesheets_load_in_the_original_order(): void
    {
        $this->seedShop();

        $html = $this->get('/')->assertOk()->getContent();

        // The theme ships an rtl.css link commented out; strip comments so a disabled
        // stylesheet is not mistaken for a loaded one.
        $active = (string) preg_replace('/<!--.*?-->/s', '', $html);

        preg_match_all('#href="(/assets/css/[^"]+)"#', $active, $m);

        // Load order is significant: theme-style.css sets the base, the per-page sheet
        // overrides it, url.css comes last. Reordering silently changes the design.
        $this->assertSame([
            '/assets/css/bootstrap-grid.min.css',
            '/assets/css/splide.min.css',
            '/assets/css/splide-core.min.css',
            '/assets/css/nouislider.css',
            '/assets/css/select2.min.css',
            '/assets/css/theme-style.css',
            '/assets/css/theme-style-home-two.css',
            '/assets/css/url.css',
        ], $m[1]);
    }

    private function seedShop(): void
    {
        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);
        $this->seed(FitmentSeederSmall::class);
        $this->seed(MerchandisingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(ContentSeeder::class);
    }

    private function allReferenceMarkup(): string
    {
        $markup = '';

        foreach (glob(base_path(self::REFERENCE_DIR).'/*.html') as $file) {
            $markup .= file_get_contents($file);
        }

        return $markup;
    }

    /** @return list<string> */
    private function classesIn(string $html): array
    {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        $classes = [];

        foreach ($matches[1] as $attribute) {
            foreach (preg_split('/\s+/', trim($attribute)) as $class) {
                if ($class !== '') {
                    $classes[$class] = true;
                }
            }
        }

        $names = array_keys($classes);
        sort($names);

        return $names;
    }
}
