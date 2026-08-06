<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Category;
use App\Support\Http\RemoteImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Give every product in a department the same photograph, in bulk.
 *
 * WHY THIS EXISTS: the purchased theme ships no product photography at all. Its "product" files
 * are 206x206 at 1kB and its four detail-page images are byte-identical — flat placeholders, not
 * pictures. So every one of the 5,000 seeded products shows a grey square, and nobody is going to
 * upload 5,000 photographs by hand. One real photo per department is eight downloads for the
 * whole catalogue, and a brake disc at least shows a brake disc.
 *
 * A PRODUCT'S OWN PHOTOGRAPHS ARE NEVER TOUCHED. That is the whole point of the design: staff can
 * give two or three products real, specific pictures for a demo, and a later bulk run must not
 * flatten them back to the department's generic one. Told apart by where the file lives:
 *
 *   assets/…                  the theme's placeholder — replaceable
 *   storage/departments/…     a previous bulk assignment — replaceable
 *   storage/products/…        somebody uploaded this on the product screen — PROTECTED
 *
 * Path-based, like the existing rule in SaveProductImagesAction that decides whether deleting an
 * image may delete its file. One convention, in both places.
 */
final class AssignDepartmentPhotosAction
{
    public const DIRECTORY = 'departments';

    /** Where an upload lands, and therefore what marks a photo as a human's choice. */
    public const UPLOAD_PREFIX = 'storage/products/';

    /** The product page has four image slots; more would be fetched and never shown. */
    public const MAX_PHOTOS = 4;

    public function __construct(private RemoteImage $remote) {}

    /**
     * Fetch each URL once and point every eligible product in the department at them.
     *
     * @param  list<string>  $urls
     * @return array{photos: int, products: int, protected: int}
     *
     * @throws RuntimeException with a message written for whoever pasted the URLs
     */
    public function assign(Category $department, array $urls): array
    {
        if ($urls === []) {
            throw new RuntimeException('Give at least one image URL.');
        }

        if (count($urls) > self::MAX_PHOTOS) {
            throw new RuntimeException('The product page shows at most '.self::MAX_PHOTOS
                .' images, so there is no point fetching more.');
        }

        // Fetched BEFORE anything is deleted. A bad URL then leaves the department exactly as it
        // was, rather than stripped of its old photos and given nothing.
        $images = array_map(fn (string $url): array => $this->remote->fetchInto($url, self::DIRECTORY), $urls);

        $productIds = $this->productsIn($department);
        $protectedIds = $this->productsWithOwnPhotos($productIds);
        $targets = array_values(array_diff($productIds, $protectedIds));

        if ($targets === []) {
            return ['photos' => count($images), 'products' => 0, 'protected' => count($protectedIds)];
        }

        DB::transaction(function () use ($targets, $images): void {
            $this->clearReplaceable($targets);
            $this->insert($targets, $images);
        });

        $this->deleteOrphanedFiles();

        Log::info('catalog.assign_department_photos.success', [
            'department' => $department->slug,
            'photos' => count($images),
            'products' => count($targets),
            'protected' => count($protectedIds),
        ]);

        return [
            'photos' => count($images),
            'products' => count($targets),
            'protected' => count($protectedIds),
        ];
    }

    /**
     * Take the department's bulk photos back off its products.
     *
     * Leaves the products with no image at all rather than restoring the theme's grey square:
     * the fallback in the views already handles a product with no image, so putting a placeholder
     * row back would be storing something meaningless in the database to say "nothing".
     *
     * @return int how many products were affected
     */
    public function clear(Category $department): int
    {
        $productIds = $this->productsIn($department);

        if ($productIds === []) {
            return 0;
        }

        $affected = 0;

        DB::transaction(function () use ($productIds, &$affected): void {
            foreach (array_chunk($productIds, 500) as $chunk) {
                $affected += DB::table('product_images')
                    ->whereIn('product_id', $chunk)
                    ->where('path', 'like', 'storage/'.self::DIRECTORY.'/%')
                    ->delete();
            }
        });

        $this->deleteOrphanedFiles();

        return $affected;
    }

    /** What this department currently shows, so the screen can display it. @return list<string> */
    public function currentPhotos(Category $department): array
    {
        $productIds = $this->productsIn($department);

        if ($productIds === []) {
            return [];
        }

        /*
         | GROUP BY rather than DISTINCT, because the order matters here — slot 0 is the card
         | image — and MySQL refuses "SELECT DISTINCT path ORDER BY position": position is not in
         | the select list, so with DISTINCT it cannot say which of the duplicate rows' positions
         | to sort by. Grouping and ordering by MIN(position) asks the question properly.
        */
        return DB::table('product_images')
            ->whereIn('product_id', array_slice($productIds, 0, 500))
            ->where('path', 'like', 'storage/'.self::DIRECTORY.'/%')
            ->groupBy('path')
            ->orderByRaw('MIN(position)')
            ->pluck('path')
            ->all();
    }

    /** How many products in the department have photographs of their own. */
    public function protectedCount(Category $department): int
    {
        return count($this->productsWithOwnPhotos($this->productsIn($department)));
    }

    /**
     * Every product filed anywhere under this department.
     *
     * By PATH, not by direct pivot rows — parts are filed against sub-categories, so counting
     * only the department's own rows reports zero. (The same mistake has been made twice in this
     * project already, both times producing a confidently wrong count on screen.)
     *
     * @return list<string>
     */
    private function productsIn(Category $department): array
    {
        return DB::table('products as p')
            ->join('product_categories as pc', 'pc.product_id', '=', 'p.id')
            ->join('categories as c', 'c.id', '=', 'pc.category_id')
            ->where('c.path', 'like', $department->path.'%')
            ->whereNull('p.deleted_at')
            ->distinct()
            ->pluck('p.id')
            ->all();
    }

    /**
     * @param  list<string>  $productIds
     * @return list<string>
     */
    private function productsWithOwnPhotos(array $productIds): array
    {
        $found = [];

        foreach (array_chunk($productIds, 500) as $chunk) {
            $found = [...$found, ...DB::table('product_images')
                ->whereIn('product_id', $chunk)
                ->where('path', 'like', self::UPLOAD_PREFIX.'%')
                ->distinct()
                ->pluck('product_id')
                ->all()];
        }

        return $found;
    }

    /** @param  list<string>  $targets */
    private function clearReplaceable(array $targets): void
    {
        foreach (array_chunk($targets, 500) as $chunk) {
            DB::table('product_images')
                ->whereIn('product_id', $chunk)
                // Only the two replaceable kinds are named, rather than deleting everything on
                // the product. An unrecognised path stays: better to leave something we do not
                // understand than to delete it.
                ->where(fn ($q) => $q
                    ->where('path', 'like', 'assets/%')
                    ->orWhere('path', 'like', 'storage/'.self::DIRECTORY.'/%'))
                ->delete();
        }
    }

    /**
     * @param  list<string>  $targets
     * @param  list<array{path: string, width: int, height: int, source: string, bytes: int}>  $images
     */
    private function insert(array $targets, array $images): void
    {
        $names = [];

        foreach (array_chunk($targets, 500) as $chunk) {
            $names += DB::table('products')->whereIn('id', $chunk)->pluck('name', 'id')->all();
        }

        $rows = [];
        $now = now();

        foreach ($targets as $productId) {
            foreach ($images as $slot => $image) {
                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'product_id' => $productId,
                    'path' => $image['path'],
                    // The product's name, not the file name. An empty alt on a shop image is an
                    // accessibility and an SEO problem, and the file name here is a ULID.
                    'alt' => $names[$productId] ?? null,
                    'position' => $slot,
                    'is_primary' => $slot === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Chunked inserts: 5,000 products times four images is 20,000 rows, and one statement
        // that size hits max_allowed_packet rather than being merely slow.
        foreach (array_chunk($rows, 1_000) as $chunk) {
            DB::table('product_images')->insert($chunk);
        }
    }

    /**
     * Remove department photo FILES nothing points at any more.
     *
     * Reassigning a department leaves its previous files on disk with no rows referencing them.
     * There are only a handful, but "a handful per run" is how a disk fills up over a year.
     */
    private function deleteOrphanedFiles(): void
    {
        $referenced = DB::table('product_images')
            ->where('path', 'like', 'storage/'.self::DIRECTORY.'/%')
            ->distinct()
            ->pluck('path')
            ->map(fn (string $path): string => substr($path, strlen('storage/')))
            ->all();

        foreach (Storage::disk('public')->files(self::DIRECTORY) as $file) {
            if (! in_array($file, $referenced, true)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
