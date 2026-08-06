<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Deletes a category, and refuses when anything still depends on it.
 *
 * Refusing is Stefan's decision, and it is the right one: the alternative — unlinking the
 * products — leaves them in the catalogue but in no category, which means they fall out of
 * every listing page and become reachable only by direct URL. Nothing on screen would say
 * that had happened.
 *
 * Hard delete rather than soft, because a category is structure rather than a record. Its
 * absence from an old receipt costs nothing: receipt lines snapshot the product's name, SKU
 * and brand, and never referenced a category.
 */
final class DeleteCategoryAction
{
    public function execute(Category $category): void
    {
        // Counted from the pivot, not from the denormalised products_count column. That
        // counter is maintained by the importer and can lag; refusing (or worse, allowing)
        // a delete on a stale number is how a department full of parts gets deleted.
        $products = $category->products()->count();

        if ($products > 0) {
            throw new RuntimeException(
                "{$category->name} cannot be deleted: {$products} "
                .($products === 1 ? 'product uses' : 'products use')
                .' it. Move them to another category first.'
            );
        }

        $children = $category->children()->count();

        if ($children > 0) {
            throw new RuntimeException(
                "{$category->name} cannot be deleted: it has {$children} "
                .($children === 1 ? 'sub-category' : 'sub-categories')
                .'. Delete or move those first.'
            );
        }

        /*
         | The foreign key on parent_id is nullOnDelete, so deleting a parent would silently
         | promote its children to top-level departments with stale paths — a whole branch
         | of the shop rearranged by one click. The check above is what prevents that; the
         | database constraint alone would have allowed it.
        */

        $category->delete();

        Log::info('catalog.delete_category.success', [
            'category_id' => $category->id,
            'path' => $category->path,
        ]);
    }
}
