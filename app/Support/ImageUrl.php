<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The one place a stored image path becomes a URL.
 *
 * Two kinds of path flow through the views and they must NOT be treated alike:
 *
 *   assets/images/banner/banner-1.jpg   the purchased theme's own files, served straight off
 *                                       public/ exactly as shipped. Never uploaded, never
 *                                       moved, and never anybody's object storage.
 *   storage/products/01j….webp          something somebody uploaded or the importer fetched.
 *                                       This is the only kind that follows the disk.
 *
 * Every view used to print "/{{ $path }}" by hand, which is correct for both kinds right up
 * until the uploads live somewhere other than this machine's disk — at which point the files
 * move and every page keeps pointing at a path on the app server that no longer holds
 * anything. That is the single thing standing between this app and any host with an
 * ephemeral filesystem, which is all of them: Render, Laravel Cloud, Vercel, a Codespace.
 *
 * WHY NOT Storage::url(). Because it bakes APP_URL into the value for the local disk, and
 * this shop is reached on several hosts — localhost:8090, a LAN IP from a phone, a tunnel,
 * a demo domain. A URL carrying the wrong host is how images "disappear and never come
 * back". So: origin-relative for local, and the disk's own absolute URL only when the files
 * genuinely live somewhere else. Same rule as {@see Assets::version()}.
 */
final class ImageUrl
{
    /**
     * Uploads are stored with this prefix already on the path (see SaveProductImagesAction
     * and friends, which strip it back off before handing a key to the disk).
     */
    private const UPLOAD_PREFIX = 'storage/';

    /**
     * A URL for a stored image path, or '' when there is nothing to show.
     *
     * Returns '' rather than throwing on an empty path: every caller already has a
     * placeholder for the no-image case, and a missing picture must never take down a page.
     */
    public static function for(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return '';
        }

        // Already a full URL — a seeded remote image, or a path that has been through here
        // once. Left exactly as it is.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        $path = ltrim($path, '/');

        // Theme asset. Always local, always origin-relative — moving these would be a change
        // to the purchased template's own markup, which is the one thing this project does
        // not do.
        if (! str_starts_with($path, self::UPLOAD_PREFIX)) {
            return '/'.$path;
        }

        $disk = config('filesystems.uploads_disk', 'public');

        // The local public disk is reached through the public/storage symlink, so the stored
        // path IS the URL. Origin-relative, for the reason in the class docblock.
        if ($disk === 'public') {
            return '/'.$path;
        }

        // Anywhere else — S3, R2, Supabase, whatever the host provides — the disk knows its
        // own public address and the stored prefix is not part of the key.
        return Storage::disk($disk)->url(substr($path, strlen(self::UPLOAD_PREFIX)));
    }

    /**
     * The disk uploads are written to and read from.
     *
     * Everything that writes an image asks for it here rather than naming 'public'
     * directly, so the write side and {@see self::for()} can never disagree about where
     * the files are — which would put images somewhere real under URLs pointing elsewhere.
     */
    public static function disk(): string
    {
        return (string) config('filesystems.uploads_disk', 'public');
    }
}
