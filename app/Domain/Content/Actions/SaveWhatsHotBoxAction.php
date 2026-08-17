<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\Banner;
use App\Support\Http\RemoteImage;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The homepage's "What's Hot" promo boxes.
 *
 * WHAT THIS SECTION ACTUALLY IS, since it is easy to mistake for a product strip: four promo
 * boxes in a carousel, each with a background image, a line of small text, a headline and a
 * button. They come from `banners` at placement home_secondary, not from products or categories.
 *
 * EVERY BOX POINTS AT A REAL CATEGORY, and that is the design decision here. The link used to be
 * a free-text field and all four boxes pointed at /shop regardless of what they said — the
 * "Alloy Wheels" box did not go to wheels. Choosing a category instead means the URL is generated
 * from something that exists, so a box can never advertise a department the shop does not have.
 * That was Stefan's worry, and picking from a list is the answer to it.
 */
final class SaveWhatsHotBoxAction
{
    public const PLACEMENT = 'home_secondary';

    private const DIRECTORY = 'whats-hot';

    /**
     * How many the carousel shows before it starts sliding.
     *
     * Not a limit — the theme's slider takes more and pages through them. It is what the admin
     * tells staff, so nobody wonders why the fifth box is not on screen.
     */
    public const VISIBLE_AT_ONCE = 4;

    public function __construct(private RemoteImage $remote) {}

    /**
     * @param  array{category_id: string, headline: string, tagline: ?string, image_url: ?string, link_label: ?string}  $input
     *
     * @throws RuntimeException with a message written for whoever filled the form
     */
    public function create(array $input): Banner
    {
        $category = $this->category($input['category_id']);

        // Fetched BEFORE the row is written, so a bad URL leaves nothing half-made.
        $image = ($input['image_url'] ?? null) === null
            ? null
            : $this->remote->fetchInto($input['image_url'], self::DIRECTORY);

        $banner = Banner::create([
            'placement' => self::PLACEMENT,
            'title' => $input['headline'],
            'subtitle' => $input['tagline'] ?: null,
            /*
             | The theme's own boxes ship a 369x450 placeholder with its dimensions printed on it.
             | Without an image the box is grey, which is honest — a made-up picture would not be
             | — so image_path falls back to the theme asset rather than to nothing, because the
             | column is NOT NULL and the view prefixes a slash to whatever it holds.
            */
            'image_path' => $image['path'] ?? 'assets/images/hot/hot-1.png',
            'source_url' => $image['source'] ?? null,
            'image_width' => $image['width'] ?? null,
            'image_height' => $image['height'] ?? null,
            'link_url' => $this->linkFor($category),
            'link_label' => $input['link_label'] ?: 'Shop Now',
            'position' => $this->nextPosition(),
            'is_active' => true,
        ]);

        Log::info('content.whats_hot_box.created', [
            'banner_id' => $banner->id,
            'category' => $category->slug,
        ]);

        return $banner;
    }

    /**
     * @param  array{category_id?: ?string, headline?: ?string, tagline?: ?string, image_url?: ?string, link_label?: ?string}  $input
     */
    public function update(Banner $banner, array $input): Banner
    {
        $changes = [];

        if (($input['headline'] ?? null) !== null) {
            $changes['title'] = $input['headline'];
        }

        // A blank tagline is a deliberate clearing here, unlike an import's blank cell: a person
        // is looking at the field they just emptied.
        if (array_key_exists('tagline', $input)) {
            $changes['subtitle'] = $input['tagline'] ?: null;
        }

        if (($input['category_id'] ?? null) !== null) {
            $changes['link_url'] = $this->linkFor($this->category($input['category_id']));
        }

        if (($input['link_label'] ?? null) !== null) {
            $changes['link_label'] = $input['link_label'];
        }

        if (($input['image_url'] ?? null) !== null) {
            $image = $this->remote->fetchInto($input['image_url'], self::DIRECTORY);

            $this->deleteFile($banner);

            $changes = [
                ...$changes,
                'image_path' => $image['path'],
                'source_url' => $image['source'],
                'image_width' => $image['width'],
                'image_height' => $image['height'],
            ];
        }

        $banner->update($changes);

        return $banner;
    }

    /** Off, not deleted — the safe control, and the one Stefan asked for by name. */
    public function setActive(Banner $banner, bool $active): Banner
    {
        $banner->update(['is_active' => $active]);

        return $banner;
    }

    public function delete(Banner $banner): void
    {
        DB::transaction(function () use ($banner): void {
            $this->deleteFile($banner);
            $banner->delete();

            // Renumbered densely, so position keeps meaning "nth box" after one is removed from
            // the middle.
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
        });

        Log::info('content.whats_hot_box.deleted', ['banner_id' => $banner->id]);
    }

    /** Move one box up or down, then renumber — same shape as the homepage sections. */
    public function move(Banner $banner, string $direction): void
    {
        DB::transaction(function () use ($banner, $direction): void {
            $ordered = Banner::query()
                ->where('placement', self::PLACEMENT)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $index = $ordered->search(fn (Banner $b): bool => $b->id === $banner->id);

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($target < 0 || $target >= $ordered->count()) {
                return;
            }

            $rows = $ordered->all();
            [$rows[$index], $rows[$target]] = [$rows[$target], $rows[$index]];

            foreach ($rows as $position => $row) {
                if ((int) $row->position !== $position) {
                    $row->update(['position' => $position]);
                }
            }
        });
    }

    /**
     * The URL for a category, generated rather than typed.
     *
     * route(), not string concatenation, so the box breaks loudly at save time if the shop's
     * category route is ever renamed — instead of quietly linking to a 404 on the homepage.
     */
    private function linkFor(Category $category): string
    {
        return route('shop.category', $category->slug, false);
    }

    private function category(string $id): Category
    {
        $category = Category::query()->where('is_active', true)->find($id);

        if ($category === null) {
            throw new RuntimeException('That category does not exist, or is switched off.');
        }

        return $category;
    }

    private function deleteFile(Banner $banner): void
    {
        // Only files WE fetched. A seeded row points at the purchased theme's own asset, which is
        // shared and not ours to remove.
        if (str_starts_with($banner->image_path, 'storage/'.self::DIRECTORY.'/')) {
            Storage::disk(ImageUrl::disk())->delete(substr($banner->image_path, strlen('storage/')));
        }
    }

    private function nextPosition(): int
    {
        $highest = Banner::query()->where('placement', self::PLACEMENT)->max('position');

        return $highest === null ? 0 : (int) $highest + 1;
    }
}
