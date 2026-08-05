<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Public;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The category tree for the site navigation.
 *
 * Read on literally every page render and changed maybe weekly, so it is cached —
 * and unlike the homepage payload, this one caches **plain arrays**. That is
 * deliberate: caching hydrated Eloquent models and Collections is what produced
 * "incomplete object" errors and a blank homepage earlier. Arrays and scalars
 * round-trip through Redis without surprises.
 */
final class GetNavigationQuery
{
    public const CACHE_KEY = 'navigation.categories';

    /**
     * @return list<array{name: string, slug: string, image: ?string, children: list<array{name: string, slug: string}>}>
     */
    public function execute(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $rows = DB::table('categories')
                ->where('is_active', true)
                ->orderBy('depth')->orderBy('position')
                ->get(['id', 'parent_id', 'name', 'slug', 'image_path', 'depth']);

            $children = [];

            foreach ($rows->where('depth', 1) as $child) {
                $children[$child->parent_id][] = [
                    'name' => $child->name,
                    'slug' => $child->slug,
                ];
            }

            return $rows->where('depth', 0)->map(fn (object $parent): array => [
                'name' => $parent->name,
                'slug' => $parent->slug,
                'image' => $parent->image_path,
                'children' => $children[$parent->id] ?? [],
            ])->values()->all();
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
