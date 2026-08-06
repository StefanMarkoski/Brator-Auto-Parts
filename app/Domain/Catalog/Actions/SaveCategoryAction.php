<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates or updates a category, keeping the materialised path and depth correct.
 *
 * The path is the load-bearing part. Listing pages resolve a whole department in one
 * indexed query with `path LIKE '/braking/%'`, so a stale path does not produce a slightly
 * wrong page — it produces an empty one, or one showing another department's parts. Moving
 * or renaming a category therefore has to rewrite every descendant's path, not just its own.
 *
 * The rewrite walks the children in PHP rather than doing one clever UPDATE with
 * SUBSTRING/CONCAT. This tree is sixteen departments and about sixty sub-categories; the
 * walk costs nothing, and it recomputes each path from its parent rather than doing string
 * surgery on a prefix, so it cannot leave a half-rewritten path behind.
 */
final class SaveCategoryAction
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $parent = $this->resolveParent($attributes['parent_id'] ?? null);

            $category = new Category([
                ...$attributes,
                'slug' => $this->uniqueSlug($attributes['slug'] ?? null, (string) $attributes['name']),
                'depth' => $parent === null ? 0 : $parent->depth + 1,
                // Last in its row of siblings, so a new category does not silently jump
                // to the front of a navigation menu somebody has ordered deliberately.
                'position' => $this->nextPosition($parent),
            ]);

            $category->parent_id = $parent?->id;
            $category->path = ($parent?->path ?? '/').$category->slug.'/';
            $category->save();

            Log::info('catalog.create_category.success', [
                'category_id' => $category->id,
                'path' => $category->path,
            ]);

            return $category;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes): Category {
            $parent = $this->resolveParent($attributes['parent_id'] ?? null);

            if ($parent !== null) {
                $this->refuseCycle($category, $parent);
            }

            $category->fill($attributes);
            $category->slug = $this->uniqueSlug(
                $attributes['slug'] ?? null,
                (string) $attributes['name'],
                exceptId: $category->id,
            );

            $category->parent_id = $parent?->id;
            $category->depth = $parent === null ? 0 : $parent->depth + 1;
            $category->path = ($parent?->path ?? '/').$category->slug.'/';
            $category->save();

            // The part that is easy to forget and expensive to get wrong.
            $this->rewriteDescendants($category);

            Log::info('catalog.update_category.success', [
                'category_id' => $category->id,
                'path' => $category->path,
            ]);

            return $category;
        });
    }

    /**
     * Recomputes every descendant's path and depth from its parent.
     *
     * Without this, renaming "Braking" to "Brakes" leaves its eight sub-categories still
     * claiming to live under /braking/ — so the department page finds nothing and the
     * sub-category pages disappear from it.
     */
    private function rewriteDescendants(Category $parent): void
    {
        foreach ($parent->children()->get() as $child) {
            $child->path = $parent->path.$child->slug.'/';
            $child->depth = $parent->depth + 1;
            $child->save();

            $this->rewriteDescendants($child);
        }
    }

    /**
     * A category cannot be moved inside its own subtree.
     *
     * Allowing it detaches the whole branch from the tree: every path involved becomes
     * unreachable from any root, so those categories vanish from the shop while still
     * sitting in the table looking fine.
     */
    private function refuseCycle(Category $category, Category $newParent): void
    {
        if ($newParent->id === $category->id) {
            throw new RuntimeException("{$category->name} cannot be its own parent.");
        }

        if (str_starts_with($newParent->path, $category->path)) {
            throw new RuntimeException(
                "{$category->name} cannot be moved inside {$newParent->name}, because "
                ."{$newParent->name} is already beneath it."
            );
        }
    }

    private function resolveParent(?string $parentId): ?Category
    {
        return $parentId === null || $parentId === ''
            ? null
            : Category::query()->findOrFail($parentId);
    }

    private function nextPosition(?Category $parent): int
    {
        return (int) Category::query()
            ->when($parent === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parent->id))
            ->max('position') + 1;
    }

    private function uniqueSlug(?string $preferred, string $name, ?string $exceptId = null): string
    {
        $base = Str::slug($preferred !== null && trim($preferred) !== '' ? $preferred : $name);
        $base = $base === '' ? 'category' : $base;

        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
