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
            'heading' => $attributes['heading'] ?? null,
            'subheading' => $attributes['subheading'] ?? null,
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
