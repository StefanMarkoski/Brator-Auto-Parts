<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\HomepageSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reordering, hiding and retitling the homepage.
 *
 * Deliberately NOT a page builder. Every section_type maps to a Blade partial cut from the
 * purchased theme's own markup, so a type the theme has no markup for would mean writing new
 * markup — the one change that is forbidden here. Staff therefore get exactly four verbs:
 * move, show/hide, retitle, and rebind which collection feeds a strip.
 *
 * Positions are rewritten as a dense 0..n sequence on every move rather than swapped in
 * place. Swapping looks simpler and rots: a duplicate or a gap in `position` leaves the
 * ORDER BY free to pick either row first, which is the same non-deterministic-order bug that
 * had page 1 and page 2 of the admin product list sharing 24 of 25 rows.
 */
final class SaveHomepageSectionAction
{
    /** Types that render a product strip, and therefore need a collection behind them. */
    public const COLLECTION_BACKED = ['best_sellers', 'essential_items', 'new_arrivals'];

    /*
     | Which types actually PRINT the text fields, so the editor can stop offering a box that
     | does nothing.
     |
     | This is here because four sections used to take a heading, flash "The homepage was
     | updated", and change nothing on the shop — the hero worst of all, since its words are the
     | first thing anybody asks to change. Three of those four now render it; `articles` renders
     | no markup at all, and `featured_makes` has one tab title and no room for a second line
     | without new CSS.
     |
     | Listed as what DOES render rather than what does not, so a section type added later
     | defaults to hiding the field until somebody wires it up. That default is the whole point:
     | an unoffered field is honest, an offered dead one is a lie.
    */
    public const HEADING_BACKED = [
        'hero_banner', 'categories_strip', 'whats_hot', 'featured_makes', 'best_sellers',
        'essential_items', 'new_arrivals', 'featured_brands', 'newsletter',
    ];

    public const SUBHEADING_BACKED = [
        'hero_banner', 'categories_strip', 'whats_hot', 'best_sellers',
        'essential_items', 'new_arrivals', 'featured_brands', 'newsletter',
    ];

    /** @param array<string, mixed> $attributes */
    public function update(HomepageSection $section, array $attributes): HomepageSection
    {
        $collectionId = $attributes['product_collection_id'] ?? null;

        if ($collectionId !== null && $collectionId !== '' && ! $this->takesCollection($section)) {
            throw new RuntimeException(
                "The {$section->section_type} section does not render a product strip, so a "
                .'collection would have nowhere to appear.'
            );
        }

        $section->update([
            /*
             | Kept as-is for a type that cannot print them, rather than nulled. The editor no
             | longer sends those fields, and `?? null` would then quietly erase a heading
             | somebody had typed — a save wiping a value it never showed you is worse than the
             | dead field it replaced. A field that IS offered and arrives empty still clears.
            */
            'heading' => in_array($section->section_type, self::HEADING_BACKED, true)
                ? ($attributes['heading'] ?? null)
                : $section->heading,
            'subheading' => in_array($section->section_type, self::SUBHEADING_BACKED, true)
                ? ($attributes['subheading'] ?? null)
                : $section->subheading,
            'product_collection_id' => $this->takesCollection($section)
                ? ($collectionId === '' ? null : $collectionId)
                : null,
            'is_visible' => (bool) ($attributes['is_visible'] ?? false),
        ]);

        Log::info('content.update_homepage_section.success', [
            'section_id' => $section->id,
            'type' => $section->section_type,
            'visible' => $section->is_visible,
        ]);

        return $section;
    }

    /** Moves a section one place up or down, then renumbers the whole page densely. */
    public function move(HomepageSection $section, string $direction): void
    {
        DB::transaction(function () use ($section, $direction): void {
            $ordered = HomepageSection::query()->orderBy('position')->orderBy('id')->get();
            $index = $ordered->search(fn (HomepageSection $s): bool => $s->id === $section->id);

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            // Already at the end it is being asked to move towards. Silently doing nothing
            // is right here — the button is disabled in the UI, so this is only reachable by
            // two clicks racing each other.
            if ($target < 0 || $target >= $ordered->count()) {
                return;
            }

            $reordered = $ordered->all();
            [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

            foreach ($reordered as $position => $row) {
                if ((int) $row->position !== $position) {
                    $row->update(['position' => $position]);
                }
            }

            Log::info('content.move_homepage_section.success', [
                'section_id' => $section->id,
                'type' => $section->section_type,
                'from' => $index,
                'to' => $target,
            ]);
        });
    }

    private function takesCollection(HomepageSection $section): bool
    {
        return in_array($section->section_type, self::COLLECTION_BACKED, true);
    }
}
