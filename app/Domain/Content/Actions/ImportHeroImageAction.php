<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Banner;
use App\Support\Http\RemoteImage;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Hero images for the homepage, added by pasting a URL.
 *
 * THE FILE IS FETCHED AND KEPT, NOT HOT-LINKED.
 *
 * Pointing the homepage's background straight at somebody else's server means the shop's first
 * impression depends on a host we do not control: it can start refusing us (one of the URLs
 * this was built against already refuses a plain server-side request), rate-limit us, change
 * the picture underneath us, or simply go away. Google's image-search URLs in particular are
 * cache links with an expiry. Fetching once and serving from our own disk turns a permanent
 * dependency into a one-off download, and it is also the only way to know what we actually got.
 *
 * The stored path is ORIGIN-RELATIVE — "storage/hero/…", no scheme, no host — for the same
 * reason product images are: Storage::url() bakes APP_URL into the value, so a shop reached on
 * a LAN IP or a staging domain would serve backgrounds pointing at wherever APP_URL happened to
 * be when the image was added.
 */
final class ImportHeroImageAction
{
    public const PLACEMENT = 'home_hero';

    /**
     * Below this width the hero visibly softens.
     *
     * The banner is a full-bleed background, so on an ordinary desktop it is painted about
     * 1900px wide. Anything narrower is being upscaled. This is not a rejection threshold —
     * staff may well want a picture we can only get small — it is what the admin warns about
     * so nobody discovers it on the homepage.
     */
    public const COMFORTABLE_WIDTH = 1600;

    private const DIRECTORY = 'hero';

    public function __construct(private RemoteImage $remote) {}

    /**
     * Fetch one URL and add it as the last hero image.
     *
     * @throws RuntimeException with a message written for the person who pasted the URL
     */
    public function import(string $url): Banner
    {
        $image = $this->remote->fetchInto($url, self::DIRECTORY);

        return DB::transaction(function () use ($image, $url): Banner {
            $banner = Banner::create([
                'placement' => self::PLACEMENT,
                'image_path' => $image['path'],
                'source_url' => $image['source'],
                'image_width' => $image['width'],
                'image_height' => $image['height'],
                // Appended, so adding an image never reshuffles the rotation staff already
                // arranged. The null check is not decoration: max() on an empty table returns
                // null, and (int) null + 1 would start the very first picture at 1 and leave
                // position 0 permanently empty — out of step with the dense renumbering that
                // delete() does.
                'position' => $this->nextPosition(),
                'is_active' => true,
            ]);

            Log::info('content.import_hero_image.success', [
                'banner_id' => $banner->id,
                'source' => $url,
                'dimensions' => $image['width'].'x'.$image['height'],
                'bytes' => $image['bytes'],
            ]);

            return $banner;
        });
    }

    private function nextPosition(): int
    {
        $highest = Banner::query()->where('placement', self::PLACEMENT)->max('position');

        return $highest === null ? 0 : (int) $highest + 1;
    }

    public function delete(Banner $banner): void
    {
        DB::transaction(function () use ($banner): void {
            // Only files WE fetched are removed from disk. The seeded rows point at the
            // purchased theme's own slider assets, which are shared and not ours to delete.
            if (str_starts_with($banner->image_path, 'storage/')) {
                Storage::disk(ImageUrl::disk())->delete(substr($banner->image_path, strlen('storage/')));
            }

            $banner->delete();

            // Renumbered densely, so "position" keeps meaning "nth in the rotation" after a
            // removal from the middle.
            $remaining = Banner::query()
                ->where('placement', self::PLACEMENT)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($remaining as $index => $row) {
                if ((int) $row->position !== $index) {
                    $row->update(['position' => $index]);
                }
            }

            Log::info('content.delete_hero_image.success', ['banner_id' => $banner->id]);
        });
    }
}
