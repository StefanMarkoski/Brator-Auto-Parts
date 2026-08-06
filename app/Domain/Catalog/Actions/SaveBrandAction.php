<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates or updates a brand.
 *
 * Simpler than a category — no tree, no path — but the slug still matters: the storefront's
 * brand links filter by it (`/shop?brand[]=bosch`), so a changed slug quietly breaks every
 * link already in the wild and any bookmark a shopper kept.
 */
final class SaveBrandAction
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Brand
    {
        $brand = Brand::create([
            ...$attributes,
            'slug' => $this->uniqueSlug($attributes['slug'] ?? null, (string) $attributes['name']),
            'position' => (int) Brand::query()->max('position') + 1,
        ]);

        Log::info('catalog.create_brand.success', ['brand_id' => $brand->id, 'slug' => $brand->slug]);

        return $brand;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Brand $brand, array $attributes): Brand
    {
        $brand->fill($attributes);
        $brand->slug = $this->uniqueSlug(
            $attributes['slug'] ?? null,
            (string) $attributes['name'],
            exceptId: $brand->id,
        );
        $brand->save();

        Log::info('catalog.update_brand.success', ['brand_id' => $brand->id, 'slug' => $brand->slug]);

        return $brand;
    }

    /**
     * Deleting refuses while products still carry the brand.
     *
     * The foreign key is nullOnDelete, so without this check the products would survive with
     * a blank brand — they stay in the catalogue and stay sellable, but the brand filter and
     * every brand link stop finding them, and the product page shows no maker. Losing that
     * on a hundred parts because of one click is not a trade worth offering.
     */
    public function delete(Brand $brand): void
    {
        $products = $brand->products()->count();

        if ($products > 0) {
            throw new RuntimeException(
                "{$brand->name} cannot be deleted: {$products} "
                .($products === 1 ? 'product carries' : 'products carry')
                .' it. Reassign them to another brand first.'
            );
        }

        DB::transaction(function () use ($brand): void {
            $brand->delete();
        });

        Log::info('catalog.delete_brand.success', ['brand_id' => $brand->id, 'slug' => $brand->slug]);
    }

    private function uniqueSlug(?string $preferred, string $name, ?string $exceptId = null): string
    {
        $base = Str::slug($preferred !== null && trim($preferred) !== '' ? $preferred : $name);
        $base = $base === '' ? 'brand' : $base;

        $slug = $base;
        $suffix = 2;

        while (Brand::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
