<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Actions;

use App\Domain\Fitment\DTOs\SavedVehicle;
use App\Domain\Fitment\Enums\FuelType;
use App\Domain\Fitment\Models\VehicleMake;
use App\Domain\Fitment\Models\VehicleModel;
use App\Domain\Fitment\Models\VehicleVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Add a vehicle to the catalogue's fitment tree.
 *
 * ONE FORM, UP TO THREE ROWS, and that is the whole design of this. A vehicle is a make, then a
 * model under it, then the variant a shopper actually picks — and making somebody create a make,
 * save, create a model, save, and only then reach the vehicle is three screens for one thought.
 * So each level is either chosen from what exists or typed here, in any combination.
 *
 * WHY THE PICKER PICKS THIS UP WITH NO FURTHER WORK: every level of the storefront's Year → Make
 * → Model → Sub Model → Engine cascade is a live query over these three tables, and the year
 * list is a MIN/MAX over the variants. Nothing is cached, nothing is enumerated in code. A row
 * written here is in the shopper's dropdowns on the next request.
 */
final class SaveVehicleAction
{
    /**
     * Typing a make that already exists REUSES it rather than making a second one.
     *
     * Matched on the slug, so "tesla", "Tesla" and " TESLA " are the same make. Without this the
     * first thing a demo does is grow two Teslas, and the picker then shows the name twice with
     * the models split between them.
     *
     * @param  array{make_id?: ?int, make_name?: ?string, model_id?: ?int, model_name?: ?string, name: string, year_from: int, year_to?: ?int, engine_code?: ?string, fuel_type?: ?string, power_kw?: ?int, engine_cc?: ?int, body_type?: ?string, is_active?: bool}  $input
     *
     * @throws RuntimeException with a message written for whoever filled the form
     */
    public function create(array $input): SavedVehicle
    {
        return DB::transaction(function () use ($input): SavedVehicle {
            [$make, $madeMake] = $this->make($input);
            [$model, $madeModel] = $this->model($make, $input);

            $name = trim($input['name']);
            $engine = $this->text($input['engine_code'] ?? null);
            $yearFrom = (int) $input['year_from'];
            $yearTo = ($input['year_to'] ?? null) === null ? null : (int) $input['year_to'];

            if ($yearTo !== null && $yearTo < $yearFrom) {
                throw new RuntimeException('The last year cannot be before the first year.');
            }

            /*
             | The same sub-model and engine over the same years is the same car. Caught here
             | rather than left to the database, because there is no unique index on this shape
             | — a real vehicle tree has legitimate near-duplicates (same engine, different
             | years, different markets) and a constraint would refuse those too.
            */
            $existing = VehicleVariant::query()
                ->where('model_id', $model->id)
                ->where('name', $name)
                ->where('year_from', $yearFrom)
                ->when($engine === null,
                    fn ($q) => $q->whereNull('engine_code'),
                    fn ($q) => $q->where('engine_code', $engine))
                ->first();

            if ($existing !== null) {
                throw new RuntimeException(
                    "{$make->name} {$model->name} {$name}".($engine === null ? '' : " {$engine}")
                    ." from {$yearFrom} is already in the catalogue."
                );
            }

            $variant = VehicleVariant::create([
                'model_id' => $model->id,
                'name' => $name,
                'year_from' => $yearFrom,
                // Null is "still in production", which is what the picker reads as "- present".
                'year_to' => $yearTo,
                'engine_code' => $engine,
                'fuel_type' => FuelType::from($input['fuel_type'] ?? FuelType::Petrol->value),
                'power_kw' => $this->number($input['power_kw'] ?? null),
                'engine_cc' => $this->number($input['engine_cc'] ?? null),
                'body_type' => $this->text($input['body_type'] ?? null),
                'is_active' => $input['is_active'] ?? true,
            ]);

            Log::info('fitment.vehicle.created', [
                'variant_id' => $variant->id,
                'make' => $make->name,
                'made_make' => $madeMake,
                'made_model' => $madeModel,
            ]);

            return new SavedVehicle($variant, $make->name, $model->name, $madeMake, $madeModel);
        });
    }

    /**
     * Off, not deleted.
     *
     * is_active is what every level of the picker filters on, so switching a vehicle off takes
     * it out of all five dropdowns while leaving the fitment rows that point at it intact.
     */
    public function setActive(VehicleVariant $variant, bool $active): void
    {
        $variant->update(['is_active' => $active]);
    }

    /**
     * @throws RuntimeException when parts are recorded as fitting this vehicle
     */
    public function delete(VehicleVariant $variant): void
    {
        $parts = DB::table('product_vehicle_fitments')
            ->where('vehicle_variant_id', $variant->id)
            ->count();

        /*
         | REFUSED while parts point at it, even though the schema would cascade them away
         | quietly. Deleting a vehicle would then silently strip those parts' fitment — the
         | products would survive and simply stop being findable by that car. Switching the
         | vehicle off achieves the visible half without destroying the record.
        */
        if ($parts > 0) {
            throw new RuntimeException(
                "This vehicle cannot be deleted: {$parts} "
                .($parts === 1 ? 'part is' : 'parts are')
                .' recorded as fitting it. Switch it off instead, or clear its fitment first.'
            );
        }

        DB::transaction(function () use ($variant): void {
            $variant->delete();
        });

        Log::info('fitment.vehicle.deleted', ['variant_id' => $variant->id]);
    }

    /** @return array{0: VehicleMake, 1: bool} */
    private function make(array $input): array
    {
        $id = $input['make_id'] ?? null;

        if ($id !== null) {
            $make = VehicleMake::query()->find($id);

            if ($make === null) {
                throw new RuntimeException('That make no longer exists. Pick another, or type a new one.');
            }

            return [$make, false];
        }

        $name = trim((string) ($input['make_name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Choose a make, or type the name of a new one.');
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            throw new RuntimeException('That make name cannot be turned into a web address. Use letters or numbers.');
        }

        // Reuse before create, so typing an existing name cannot fork the tree.
        $existing = VehicleMake::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return [VehicleMake::create([
            'name' => $name,
            'slug' => $slug,
            // No logo. The theme's make strip shows the NAME, and pointing a new make at one of
            // the theme's borrowed logos is how a Tesla ends up wearing somebody else's mark.
            'logo_path' => null,
            'position' => (int) (VehicleMake::query()->max('position') ?? -1) + 1,
            'is_active' => true,
        ]), true];
    }

    /** @return array{0: VehicleModel, 1: bool} */
    private function model(VehicleMake $make, array $input): array
    {
        $id = $input['model_id'] ?? null;

        if ($id !== null) {
            $model = VehicleModel::query()->where('make_id', $make->id)->find($id);

            /*
             | Scoped to the make, which is the case a plain findOrFail would wave through: the
             | model select is filled per make in the browser, so a stale page can post a Golf
             | with Tesla chosen and quietly file it under the wrong marque.
            */
            if ($model === null) {
                throw new RuntimeException("That model does not belong to {$make->name}. Pick it again.");
            }

            return [$model, false];
        }

        $name = trim((string) ($input['model_name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Choose a model, or type the name of a new one.');
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            throw new RuntimeException('That model name cannot be turned into a web address. Use letters or numbers.');
        }

        // Unique per make, not globally: two marques may both sell a "500".
        $existing = VehicleModel::query()->where('make_id', $make->id)->where('slug', $slug)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return [VehicleModel::create([
            'make_id' => $make->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]), true];
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function number(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
