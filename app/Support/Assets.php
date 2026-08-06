<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cache-busting for the handful of assets no bundler touches.
 *
 * The storefront deliberately has no build step — its script is served straight off disk like
 * the theme's own files — which also means nothing fingerprints it. Browsers and any proxy in
 * front of the shop are then free to keep serving a copy they already have, so a fix ships and
 * returning visitors carry on running the old script. That is not hypothetical: it happened
 * while building the hero cross-fade, where the correct file was on the server and the browser
 * kept the previous one.
 *
 * The theme's own /assets files are deliberately NOT run through this. They are byte-identical
 * to what was purchased and never change, and rewriting their URLs would be a change to the
 * template's own markup for no benefit.
 */
final class Assets
{
    /**
     * A root-relative URL for one of our own files, stamped with its modified time.
     *
     * Origin-relative on purpose — no scheme and no host — for the same reason stored image
     * paths are: baking APP_URL in means a shop reached on a LAN IP or a staging domain asks
     * the wrong host for its own script.
     */
    public static function version(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $file = public_path(ltrim($path, '/'));

        // A missing file still gets a usable URL rather than an exception: a 404 on a
        // progressive-enhancement script degrades to a working page, while a hard failure
        // here would take down every storefront view.
        $stamp = is_file($file) ? filemtime($file) : false;

        return $stamp === false ? $path : $path.'?v='.$stamp;
    }
}
