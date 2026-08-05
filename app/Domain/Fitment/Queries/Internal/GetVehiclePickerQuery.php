<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Queries\Internal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Feeds the theme's Year -> Make -> Model -> Sub Model -> Engine picker.
 *
 * Each level is its own small query so the browser can fetch the next step as the
 * shopper narrows down, rather than shipping the whole vehicle tree (2,000 variants)
 * into every page.
 */
final class GetVehiclePickerQuery
{
    /** @return list<int> */
    public function years(): array
    {
        return Cache::remember('fitment.years', now()->addDay(), function (): array {
            $row = DB::table('vehicle_variants')
                ->selectRaw('MIN(year_from) as lo, MAX(COALESCE(year_to, year_from)) as hi')
                ->first();

            $hi = min((int) ($row->hi ?? 0), (int) date('Y') + 1);
            $lo = (int) ($row->lo ?? $hi);

            return $lo === 0 ? [] : range($hi, $lo);
        });
    }

    /** @return list<array{id: int, name: string}> */
    public function makes(?int $year = null): array
    {
        return DB::table('vehicle_makes as mk')
            ->where('mk.is_active', true)
            ->when($year !== null, fn ($q) => $q->whereExists(
                fn ($sub) => $sub->select(DB::raw(1))
                    ->from('vehicle_models as m')
                    ->join('vehicle_variants as v', 'v.model_id', '=', 'm.id')
                    ->whereColumn('m.make_id', 'mk.id')
                    ->where('v.year_from', '<=', $year)
                    ->where(fn ($w) => $w->whereNull('v.year_to')->orWhere('v.year_to', '>=', $year))
            ))
            ->orderBy('mk.name')
            ->get(['mk.id', 'mk.name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    public function models(int $makeId, ?int $year = null): array
    {
        return DB::table('vehicle_models as m')
            ->where('m.make_id', $makeId)
            ->where('m.is_active', true)
            ->when($year !== null, fn ($q) => $q->whereExists(
                fn ($sub) => $sub->select(DB::raw(1))
                    ->from('vehicle_variants as v')
                    ->whereColumn('v.model_id', 'm.id')
                    ->where('v.year_from', '<=', $year)
                    ->where(fn ($w) => $w->whereNull('v.year_to')->orWhere('v.year_to', '>=', $year))
            ))
            ->orderBy('m.name')
            ->get(['m.id', 'm.name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])
            ->all();
    }

    /** @return list<array{id: int, name: string, engine: ?string, years: string}> */
    public function variants(int $modelId, ?int $year = null): array
    {
        return DB::table('vehicle_variants as v')
            ->where('v.model_id', $modelId)
            ->where('v.is_active', true)
            ->when($year !== null, fn ($q) => $q
                ->where('v.year_from', '<=', $year)
                ->where(fn ($w) => $w->whereNull('v.year_to')->orWhere('v.year_to', '>=', $year)))
            ->orderBy('v.name')
            ->get(['v.id', 'v.name', 'v.engine_code', 'v.year_from', 'v.year_to'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'engine' => $r->engine_code,
                'years' => $r->year_from.' - '.($r->year_to ?? 'present'),
            ])->all();
    }

    /**
     * Distinct sub-model names for a model — the theme's "Sub Model" dropdown.
     *
     * @return list<string>
     */
    public function variantNames(int $modelId, ?int $year = null): array
    {
        return DB::table('vehicle_variants as v')
            ->where('v.model_id', $modelId)
            ->where('v.is_active', true)
            ->when($year !== null, fn ($q) => $q
                ->where('v.year_from', '<=', $year)
                ->where(fn ($w) => $w->whereNull('v.year_to')->orWhere('v.year_to', '>=', $year)))
            ->distinct()
            ->orderBy('v.name')
            ->pluck('v.name')
            ->all();
    }

    /**
     * The engines available for one sub-model — the theme's "Engine" dropdown, and the
     * level at which an actual variant is finally identified.
     *
     * @return list<array{id: int, label: string}>
     */
    public function engines(int $modelId, string $variantName, ?int $year = null): array
    {
        return DB::table('vehicle_variants as v')
            ->where('v.model_id', $modelId)
            ->where('v.name', $variantName)
            ->where('v.is_active', true)
            ->when($year !== null, fn ($q) => $q
                ->where('v.year_from', '<=', $year)
                ->where(fn ($w) => $w->whereNull('v.year_to')->orWhere('v.year_to', '>=', $year)))
            ->orderBy('v.engine_code')
            ->get(['v.id', 'v.engine_code', 'v.power_kw', 'v.year_from', 'v.year_to'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'label' => trim(($r->engine_code ?? '').' '.($r->power_kw ? $r->power_kw.'kW' : ''))
                    .' ('.$r->year_from.'-'.($r->year_to ?? 'now').')',
            ])->all();
    }

    /** The chosen vehicle, spelled out for the "Parts for your ..." bar. */
    public function selection(?int $variantId): ?array
    {
        if ($variantId === null) {
            return null;
        }

        $row = DB::table('vehicle_variants as v')
            ->join('vehicle_models as m', 'm.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'm.make_id')
            ->where('v.id', $variantId)
            ->first(['v.id', 'v.name', 'v.engine_code', 'v.year_from', 'v.year_to', 'm.name as model', 'mk.name as make']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'label' => trim("{$row->make} {$row->model} {$row->name} {$row->engine_code}"),
            'years' => $row->year_from.' - '.($row->year_to ?? 'present'),
        ];
    }
}
