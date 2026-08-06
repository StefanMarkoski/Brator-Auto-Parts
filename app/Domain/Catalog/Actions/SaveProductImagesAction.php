<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Product photographs.
 *
 * `product_images` and the storefront's use of it both predate this: the product page reads
 * $product->images[0..3] and falls back to the theme's own placeholder files. Nothing could
 * WRITE the table, so every product created by hand showed a grey 750x750 box.
 *
 * THE PATH IS STORED ORIGIN-RELATIVE — "storage/products/…", with no scheme and no host —
 * and the views prefix a single slash. Storage::url() is deliberately not used: it bakes
 * APP_URL into the stored value, so a shop reached on a different host (a LAN IP, a staging
 * domain, behind a proxy) serves image tags pointing at wherever APP_URL happened to be set
 * when the file was uploaded. That has cost a day on another project.
 *
 * Seeded rows hold theme asset paths ("assets/images/product-01.jpg") and uploads hold
 * "storage/…". Both are relative to the document root, so one template handles both without
 * knowing which it has.
 */
final class SaveProductImagesAction
{
    private const DIRECTORY = 'products';

    /**
     * @param  list<UploadedFile>  $files
     * @return int how many were stored
     */
    public function upload(Product $product, array $files): int
    {
        if ($files === []) {
            return 0;
        }

        return DB::transaction(function () use ($product, $files): int {
            /*
             | A PLACEHOLDER IS NOT A PHOTOGRAPH, and the first real one clears them out.
             |
             | Every seeded product carries the theme's grey square, and a department bulk photo is
             | a generic stand-in shared by hundreds of parts. Both sit at position 0 with
             | is_primary set — so without this, uploading a real photograph left it at position 1
             | BEHIND the grey square: the card still showed the placeholder, and so did the
             | product page, which reads its four slots in position order. Somebody would have had
             | to upload and then also click "Make main", every time.
             |
             | Only a previous upload counts as a real photograph worth keeping alongside.
            */
            $ownsPhotographs = $product->images()
                ->where('path', 'like', 'storage/'.self::DIRECTORY.'/%')
                ->exists();

            if (! $ownsPhotographs) {
                /*
                 | Rows only, never the files. A theme asset belongs to the purchased template and
                 | a department photo is referenced by every other product in that department —
                 | deleting either file here would blank images across the shop.
                */
                $product->images()
                    ->where(fn ($q) => $q
                        ->where('path', 'like', 'assets/%')
                        ->orWhere('path', 'like', 'storage/'.AssignDepartmentPhotosAction::DIRECTORY.'/%'))
                    ->delete();
            }

            /*
             | -1 when there are none, so the first image lands at position 0 like every other
             | slot-numbered thing here. max() on an empty set returns null and (int) null is 0,
             | which would start the first photograph at 1 and leave slot 0 permanently empty —
             | the same trap the hero importer had.
            */
            $highest = $product->images()->max('position');
            $position = $highest === null ? -1 : (int) $highest;

            $hasPrimary = $product->images()->where('is_primary', true)->exists();
            $stored = 0;

            foreach ($files as $file) {
                $path = $file->store(self::DIRECTORY, 'public');

                if ($path === false) {
                    // Do not record a row for a file that is not on disk: the storefront
                    // would render a broken image and nothing would say why.
                    Log::warning('catalog.save_product_images.store_failed', [
                        'product_id' => $product->id,
                        'original' => $file->getClientOriginalName(),
                    ]);

                    continue;
                }

                $position++;

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => 'storage/'.$path,
                    // The product name is a better alt than the upload's filename, which is
                    // usually a camera serial. Empty alt on a shop image is an accessibility
                    // and an SEO problem.
                    'alt' => $product->name,
                    'position' => $position,
                    // First image on a product with none becomes the primary, so a product
                    // can never have photographs but no card image.
                    'is_primary' => ! $hasPrimary && $stored === 0,
                ]);

                $stored++;
            }

            Log::info('catalog.save_product_images.success', [
                'product_id' => $product->id,
                'stored' => $stored,
            ]);

            return $stored;
        });
    }

    public function delete(ProductImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $product = $image->product;
            $wasPrimary = $image->is_primary;

            // Only files WE uploaded are removed. A seeded row points at a theme asset that
            // is part of the purchased template and shared by thousands of products;
            // deleting one product's image must not delete the theme's file.
            if (str_starts_with($image->path, 'storage/')) {
                Storage::disk('public')->delete(substr($image->path, strlen('storage/')));
            }

            $image->delete();

            // Promote the next one, or the product keeps photographs while its card falls
            // back to a placeholder.
            if ($wasPrimary && $product !== null) {
                $product->images()->orderBy('position')->first()?->update(['is_primary' => true]);
            }

            Log::info('catalog.delete_product_image.success', [
                'image_id' => $image->id,
                'was_primary' => $wasPrimary,
            ]);
        });
    }

    public function makePrimary(ProductImage $image): void
    {
        DB::transaction(function () use ($image): void {
            // Cleared first: is_primary is not a unique index, so two primaries is a state
            // the database will happily hold and the card read would then pick arbitrarily.
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);
        });
    }

    /** Moves one image up or down, then renumbers densely — same reasoning as the homepage. */
    public function move(ProductImage $image, string $direction): void
    {
        DB::transaction(function () use ($image, $direction): void {
            $ordered = ProductImage::query()
                ->where('product_id', $image->product_id)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $index = $ordered->search(fn (ProductImage $i): bool => $i->id === $image->id);

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
}
