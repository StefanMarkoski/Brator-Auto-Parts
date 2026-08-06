<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * A redirect target that came from the page, made safe to use.
 *
 * Several storefront controls post where they want to go back to — clearing the vehicle,
 * clearing the filters — so the value is user input. Reflecting it unchecked makes an OPEN
 * REDIRECT: a link on this shop's own domain that lands somebody on another site, which is
 * how a convincing phishing page gets its convincing URL.
 *
 * Only same-site PATHS are allowed through. Anything with a scheme, a host, or a protocol-
 * relative "//" prefix falls back to a known-good route.
 */
final class SafeRedirect
{
    public static function path(?string $candidate, string $fallback): string
    {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            return $fallback;
        }

        // Must be a root-relative path. "//evil.example.com" is a valid URL to a DIFFERENT
        // host that begins with a slash, which is exactly the case a naive
        // str_starts_with('/') check waves through.
        if (! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return $fallback;
        }

        // A backslash is treated as a slash by some browsers, so "/\evil.example.com" is the
        // same trick wearing a different hat.
        if (str_contains($candidate, '\\')) {
            return $fallback;
        }

        // Belt and braces: if it parses as having a scheme or host, it is not a bare path.
        $parts = parse_url($candidate);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $fallback;
        }

        return $candidate;
    }
}
