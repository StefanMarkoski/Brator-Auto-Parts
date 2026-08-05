<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the project's hardest rule: the storefront's rendered markup must stay
 * identical to the bought theme.
 *
 * The reference copies in resources/theme-reference/ are the original template
 * files, untouched. This test renders our Blade version and compares it against
 * the reference, allowing only the transforms we deliberately applied.
 *
 * If this test fails, either the theme markup was changed (not allowed) or a new
 * deliberate transform needs adding to transforms() below — with a reason.
 */
final class ThemeFidelityTest extends TestCase
{
    private const REFERENCE = 'resources/theme-reference/index-2.html';

    public function test_homepage_markup_matches_the_original_template(): void
    {
        $rendered = $this->get('/')->assertOk()->getContent();
        $expected = $this->transforms(file_get_contents(base_path(self::REFERENCE)));

        $this->assertSame(
            $this->normalise($expected),
            $this->normalise($rendered),
            'The rendered homepage no longer matches the original theme markup. '
            .'The theme is off-limits — revert the markup change, or if this is an '
            .'intentional transform, add it to transforms() with a written reason.'
        );
    }

    public function test_every_asset_the_homepage_references_is_root_relative(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/(?:href|src|data-src|data-bg)="((?:\.\/)?assets\/[^"]+)"/', $html, $m);

        $this->assertEmpty(
            $m[1],
            'These asset URLs are page-relative and will 404 on nested routes such as '
            ."/product/{slug}. They must start with a leading slash:\n  "
            .implode("\n  ", array_unique($m[1]))
        );
    }

    public function test_the_theme_stylesheets_load_in_the_original_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The theme ships an rtl.css link commented out; strip comments so a
        // disabled stylesheet isn't mistaken for a loaded one.
        $active = (string) preg_replace('/<!--.*?-->/s', '', $html);

        preg_match_all('#href="(/assets/css/[^"]+)"#', $active, $m);

        // Load order is significant: theme-style.css sets the base, the per-page
        // sheet overrides it, url.css comes last. Reordering silently changes the
        // design, which is why the order is asserted and not just the presence.
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

    /**
     * The only changes we deliberately make to theme markup.
     */
    private function transforms(string $html): string
    {
        return str_replace(
            [
                // Asset paths become root-relative so they resolve from nested URLs.
                '="./assets/',
                '="assets/',
                // The theme's hardcoded demo title becomes a per-page yield.
                '<title>Brator Home Style Two</title>',
            ],
            [
                '="/assets/',
                '="/assets/',
                '<title>Brator Auto Parts</title>',
            ],
            $html
        );
    }

    /**
     * Collapse whitespace. Blade's include handling shifts indentation, which has
     * no effect on rendering — HTML collapses runs of whitespace to a single space.
     * Everything that can actually change the page still fails the comparison.
     */
    private function normalise(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }
}
