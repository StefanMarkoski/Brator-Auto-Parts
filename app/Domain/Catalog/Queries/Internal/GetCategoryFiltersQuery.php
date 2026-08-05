<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries\Internal;

use Illuminate\Support\Facades\DB;

/**
 * Which filters belong on this listing page, and what their options are.
 *
 * Scoped through category_attributes, so someone browsing oil filters is never asked
 * about wheel offset. Without a category (search results) it falls back to every
 * filterable attribute.
 */
final class GetCategoryFiltersQuery
{
    /**
     * @return list<array{code: string, label: string, unit: ?string, widget: string, options: list<array{value: string, swatch: ?string}>}>
     */
    public function execute(?string $categoryId): array
    {
        $attributes = DB::table('attributes as a')
            ->where('a.is_filterable', true)
            ->when($categoryId !== null, fn ($q) => $q->join(
                'category_attributes as ca',
                fn ($join) => $join->on('ca.attribute_id', '=', 'a.id')->where('ca.category_id', $categoryId)
            ))
            ->orderBy('a.position')
            ->get(['a.id', 'a.code', 'a.label', 'a.unit', 'a.filter_widget', 'a.type']);

        $options = DB::table('attribute_options')
            ->whereIn('attribute_id', $attributes->pluck('id'))
            ->orderBy('position')
            ->get(['attribute_id', 'value', 'swatch_hex'])
            ->groupBy('attribute_id');

        return $attributes->map(fn (object $attribute): array => [
            'code' => $attribute->code,
            'label' => $attribute->label,
            'unit' => $attribute->unit,
            'widget' => $attribute->filter_widget,
            'options' => ($options[$attribute->id] ?? collect())
                ->map(fn (object $option): array => [
                    'value' => $option->value,
                    'swatch' => $option->swatch_hex,
                ])->values()->all(),
        ])->values()->all();
    }
}
