<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Public;

use App\Domain\Catalog\Queries\Internal\ResolveCollectionQuery;
use App\Domain\Content\Models\Banner;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The whole homepage in one payload.
 *
 * NOT cached, deliberately, and worth explaining because caching this looks obvious.
 *
 * The payload contains hydrated Eloquent models and Collections. Serialising those
 * into Redis and back does not round-trip reliably — the first attempt here produced
 * "incomplete object" errors on read, which surfaced as a blank homepage. Caching
 * hydrated objects is a known footgun.
 *
 * When this page needs caching (lever 3 in the schema plan), cache a payload of plain
 * arrays and scalars built for the cache, not the object graph the views happen to
 * want today. Until the page is measurably slow, no cache is the honest option.
 */
final class GetHomepageQuery
{
    public function __construct(private ResolveCollectionQuery $collections) {}

    /** @return array{sections: Collection<int, object>} */
    public function execute(): array
    {
        return ['sections' => $this->buildSections()];
    }

    /** @return Collection<int, object> */
    private function buildSections(): Collection
    {
        $sections = HomepageSection::query()->visible()->with('collection')->get();

        return $sections->map(fn (HomepageSection $section) => (object) [
            'type' => $section->section_type,
            'view' => $section->viewName(),
            'heading' => $section->heading,
            'subheading' => $section->subheading,
            'items' => $this->itemsFor($section),
        ]);
    }

    private function itemsFor(HomepageSection $section): mixed
    {
        return match ($section->section_type) {
            'hero_banner' => Banner::query()->live()->where('placement', 'home_hero')->get(),

            // "What's Hot" is NOT a product strip — the theme renders it as four promo
            // boxes with background images and four distinct designs. It is fed by
            // banners, not by a product collection.
            'whats_hot' => Banner::query()->live()->where('placement', 'home_secondary')->get(),

            'categories_strip' => DB::table('categories')
                ->where('depth', 0)->where('is_active', true)
                ->orderBy('position')
                ->select(['name', 'slug', 'image_path', 'products_count'])
                ->get(),

            'featured_makes' => DB::table('vehicle_makes')
                ->where('is_active', true)->orderBy('position')->limit(24)
                ->select(['name', 'slug', 'logo_path'])
                ->get(),

            'featured_brands' => DB::table('brands')
                ->where('is_active', true)->orderBy('position')->limit(18)
                ->select(['name', 'slug', 'logo_path'])
                ->get(),

            'articles' => Post::query()->published()
                ->with('category')
                ->orderByDesc('published_at')->limit(4)->get(),

            'best_sellers', 'essential_items', 'new_arrivals' => $this->collections->execute($section->collection),

            default => collect(),
        };
    }
}
