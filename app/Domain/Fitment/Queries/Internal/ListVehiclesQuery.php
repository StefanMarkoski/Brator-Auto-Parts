<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Queries\Internal;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The admin's vehicle list, and the counts underneath the picker.
 *
 * Internal, not Public: this is the panel's own view of the fitment tree — every vehicle
 * including the switched-off ones, with how many parts point at each — and no other context
 * has any business reading it.
 */
final class ListVehiclesQuery
{
    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $needle = trim((string) $search);

        return DB::table('vehicle_variants as v')
            ->join('vehicle_models as m', 'm.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'm.make_id')
            // A count per row rather than a join, so a vehicle with 400 parts is still one row.
            ->selectSub(
                DB::table('product_vehicle_fitments as f')
                    ->selectRaw('count(*)')
                    ->whereColumn('f.vehicle_variant_id', 'v.id'),
                'parts_count'
            )
            ->when($needle !== '', fn ($q) => $q->where(function ($w) use ($needle): void {
                // One box for all three names, because staff think "golf", not "which column".
                $like = '%'.$needle.'%';
                $w->where('mk.name', 'like', $like)
                    ->orWhere('m.name', 'like', $like)
                    ->orWhere('v.name', 'like', $like)
                    ->orWhere('v.engine_code', 'like', $like);
            }))
            ->orderBy('mk.name')
            ->orderBy('m.name')
            ->orderBy('v.name')
            ->orderBy('v.year_from')
            ->addSelect([
                'v.id', 'v.name', 'v.engine_code', 'v.year_from', 'v.year_to', 'v.fuel_type',
                'v.power_kw', 'v.is_active', 'm.name as model', 'mk.name as make', 'mk.id as make_id',
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Every make with its models, for the "choose or type" pair on the form.
     *
     * Sent whole rather than fetched per make over the network: the switched-off ones are
     * included so staff can see a model already exists instead of making a second one, and this
     * tree is small — a real one would page, and then this would become an endpoint.
     *
     * @return list<array{id: int, name: string, is_active: bool, models: list<array{id: int, name: string}>}>
     */
    public function makesWithModels(): array
    {
        $models = DB::table('vehicle_models')
            ->orderBy('name')
            ->get(['id', 'make_id', 'name'])
            ->groupBy('make_id');

        return DB::table('vehicle_makes')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (object $make): array => [
                'id' => (int) $make->id,
                'name' => $make->name,
                'is_active' => (bool) $make->is_active,
                'models' => ($models[$make->id] ?? collect())
                    ->map(fn (object $model): array => ['id' => (int) $model->id, 'name' => $model->name])
                    ->values()->all(),
            ])->values()->all();
    }

    /**
     * What the shopper's picker is offering RIGHT NOW.
     *
     * The point of the screen: these five numbers are read from the same tables the storefront
     * cascade queries, counted the same way it counts — active rows only, years spanning from
     * the earliest first year to the latest last year. Add a vehicle and they move.
     *
     * @return array{years: int, first_year: ?int, last_year: ?int, makes: int, models: int, vehicles: int, hidden: int}
     */
    public function pickerCounts(): array
    {
        $span = DB::table('vehicle_variants')
            ->where('is_active', true)
            ->selectRaw('MIN(year_from) as lo, MAX(COALESCE(year_to, year_from)) as hi')
            ->first();

        $lo = (int) ($span->lo ?? 0);
        $hi = (int) ($span->hi ?? 0);

        return [
            'years' => ($lo === 0 || $hi < $lo) ? 0 : ($hi - $lo + 1),
            'first_year' => $lo === 0 ? null : $lo,
            'last_year' => $hi === 0 ? null : $hi,
            // Counted through the variants, so a make with nothing under it is not advertised as
            // something the picker offers — because the picker's own query would not show it.
            'makes' => DB::table('vehicle_makes as mk')->where('mk.is_active', true)
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('vehicle_models as m')
                    ->join('vehicle_variants as v', 'v.model_id', '=', 'm.id')
                    ->whereColumn('m.make_id', 'mk.id')
                    ->where('v.is_active', true))
                ->count(),
            'models' => DB::table('vehicle_models as m')->where('m.is_active', true)
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('vehicle_variants as v')
                    ->whereColumn('v.model_id', 'm.id')
                    ->where('v.is_active', true))
                ->count(),
            'vehicles' => DB::table('vehicle_variants')->where('is_active', true)->count(),
            'hidden' => DB::table('vehicle_variants')->where('is_active', false)->count(),
        ];
    }
}
