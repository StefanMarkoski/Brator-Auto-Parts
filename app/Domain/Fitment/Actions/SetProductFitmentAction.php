<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Record which vehicles a part fits.
 *
 * FITMENT OWNS THIS WRITE. The importer knows how to read a feed; it has no business deciding
 * what identifies a vehicle or what "fits" means. Keeping the resolution here means the rule is
 * in one place when a second feed, or a screen, needs to say the same thing.
 *
 * A reference may be written either way a real feed writes it:
 *
 *   BXE                          an engine code — what a supplier catalogue actually carries
 *   Volkswagen Golf V 1.9 TDI    make, model and engine, as a human would say it
 *
 * Engine code is tried first because it is the precise one. The written form exists because a
 * feed built by a person in a spreadsheet will use it, and refusing that would mean the column
 * only works for suppliers who already have our internal vocabulary.
 */
final class SetProductFitmentAction
{
    /**
     * The vehicle list, built at most once per instance.
     *
     * An instance property rather than a static: the importer takes this action in its
     * constructor, so the cache lives exactly as long as one import run, which is the lifetime
     * that is actually correct. A static would survive into the next run — and, worse, between
     * tests, where the database has been rolled back underneath it.
     *
     * @var array<string, int>|null
     */
    private ?array $vehicles = null;

    /**
     * Attach the given vehicles to a product.
     *
     * ADDITIVE, never a sync. A feed listing three vehicles must not delete fitment recorded
     * from another source — the same reasoning that stops an import dropping categories a human
     * attached by hand. Removing fitment is a deliberate act, not a side effect of an import.
     *
     * A null product id means "resolve but write nothing" — what an import preview needs, where
     * the product does not exist yet and the only question is whether the names are recognised.
     *
     * @param  list<string>  $references
     * @return array{matched: int, unknown: list<string>, ambiguous: list<string>}
     */
    public function apply(?string $productId, array $references, bool $dryRun = false): array
    {
        $dryRun = $dryRun || $productId === null;

        if ($references === []) {
            return ['matched' => 0, 'unknown' => [], 'ambiguous' => []];
        }

        $lookup = $this->lookup();
        $matched = [];
        $unknown = [];
        $ambiguous = [];

        foreach ($references as $reference) {
            $key = $this->normalise($reference);

            if ($key === '') {
                continue;
            }

            $candidates = $lookup[$key] ?? [];

            if ($candidates === []) {
                $unknown[] = $reference;

                continue;
            }

            /*
             | AN ENGINE CODE IS NOT UNIQUE, and guessing would be the wrong kind of helpful.
             | Twenty of the codes in this catalogue name more than one car — Renault's K9K
             | alone appears in nine — because the same engine goes in several models. A part
             | that fits the engine may well not fit the car: a brake disc for a Golf VI is not
             | a brake disc for a Passat B7 just because both run a CAYC.
             |
             | So an ambiguous reference matches nothing and is reported back. The feed can say
             | "Volkswagen Golf VI 1.6 TDI" instead, which names exactly one car.
            */
            if (count($candidates) > 1) {
                $ambiguous[] = $reference;

                continue;
            }

            // Keyed by variant id, so the same vehicle named twice in one row — once by code
            // and once in words — counts once.
            $matched[$candidates[0]] = true;
        }

        if ($matched === [] || $dryRun) {
            return [
                'matched' => count($matched),
                'unknown' => $unknown,
                'ambiguous' => $ambiguous,
            ];
        }

        $rows = [];

        foreach (array_keys($matched) as $variantId) {
            $rows[] = [
                'vehicle_variant_id' => $variantId,
                'product_id' => $productId,
                // A feed that says "fits" without qualification means the whole production
                // run. Inventing a year window here would be worse than leaving it open.
                'year_from' => null,
                'year_to' => null,
                'note' => null,
            ];
        }

        // insertOrIgnore, so re-running the same feed is harmless rather than a duplicate-key
        // failure that abandons the rest of the run.
        DB::table('product_vehicle_fitments')->insertOrIgnore($rows);

        return [
            'matched' => count($matched),
            'unknown' => $unknown,
            'ambiguous' => $ambiguous,
        ];
    }

    /**
     * Add these vehicles, by id, leaving anything already recorded alone.
     *
     * For the create screen: staff pick vehicles from the cascade, so the ids are already
     * resolved and there is no name to interpret.
     *
     * @param  list<int>  $variantIds
     * @return int how many were newly recorded
     */
    public function attach(string $productId, array $variantIds): int
    {
        $variantIds = $this->existingOnly($variantIds);

        if ($variantIds === []) {
            return 0;
        }

        $before = DB::table('product_vehicle_fitments')->where('product_id', $productId)->count();

        DB::table('product_vehicle_fitments')->insertOrIgnore(array_map(
            fn (int $id): array => [
                'vehicle_variant_id' => $id,
                'product_id' => $productId,
                'year_from' => null,
                'year_to' => null,
                'note' => null,
            ],
            $variantIds,
        ));

        return DB::table('product_vehicle_fitments')->where('product_id', $productId)->count() - $before;
    }

    /**
     * Make the product's fitment exactly this list.
     *
     * THE ONE PLACE A SYNC IS RIGHT. Everywhere else fitment is additive, because a feed does
     * not know what another source recorded. Here a person is looking at the whole list on
     * screen and has decided what it should be — removing a row is the point of the control, and
     * refusing to remove it would make the screen a liar.
     *
     * @param  list<int>  $variantIds
     * @return array{added: int, removed: int}
     */
    public function sync(string $productId, array $variantIds): array
    {
        $wanted = $this->existingOnly($variantIds);

        $current = DB::table('product_vehicle_fitments')
            ->where('product_id', $productId)
            ->pluck('vehicle_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $remove = array_values(array_diff($current, $wanted));
        $add = array_values(array_diff($wanted, $current));

        if ($remove !== []) {
            DB::table('product_vehicle_fitments')
                ->where('product_id', $productId)
                ->whereIn('vehicle_variant_id', $remove)
                ->delete();
        }

        if ($add !== []) {
            $this->attach($productId, $add);
        }

        return ['added' => count($add), 'removed' => count($remove)];
    }

    /**
     * Only ids that are real vehicles.
     *
     * The ids arrive from a form, so they are input. A missing check would either throw on a
     * foreign key or, on a schema without one, record fitment against a vehicle that does not
     * exist — invisible until somebody wonders why a part appears for nothing.
     *
     * @param  list<int>  $variantIds
     * @return list<int>
     */
    private function existingOnly(array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));

        if ($variantIds === []) {
            return [];
        }

        return DB::table('vehicle_variants')
            ->whereIn('id', $variantIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Every way a vehicle can be named, mapped to the ids that name matches.
     *
     * A LIST per key, not one id, because engine codes are shared between models. Storing one
     * would mean the last row loaded silently won and a feed got a different car than it asked
     * for, with nothing anywhere saying so.
     *
     * Built once and reused for the rest of the run. There are 82 variants here, but a real
     * vehicle tree is tens of thousands of rows, and rebuilding this per product would be most
     * of the cost of the whole import.
     *
     * @return array<string, list<int>>
     */
    private function lookup(): array
    {
        if ($this->vehicles !== null) {
            return $this->vehicles;
        }

        $rows = DB::table('vehicle_variants as v')
            ->join('vehicle_models as mo', 'mo.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'mo.make_id')
            ->get(['v.id', 'v.name', 'v.engine_code', 'mo.name as model', 'mk.name as make']);

        $lookup = [];

        foreach ($rows as $row) {
            if ($row->engine_code !== null && $row->engine_code !== '') {
                $lookup[$this->normalise($row->engine_code)][] = (int) $row->id;
            }

            // Make + model + engine names exactly one car, so this key is the unambiguous way
            // to write a reference and the answer when a code turns out to be shared.
            $lookup[$this->normalise($row->make.' '.$row->model.' '.$row->name)][] = (int) $row->id;
        }

        return $this->vehicles = $lookup;
    }

    /**
     * One spelling for comparison.
     *
     * Case and spacing are collapsed because a feed will write "1.9 TDI", "1.9  tdi" and
     * "1.9tdi" for the same engine, and a fitment that fails to match on whitespace is a part
     * that silently never appears for the car it fits.
     */
    private function normalise(string $value): string
    {
        return Str::squish(Str::lower(str_replace(['_', '-'], ' ', $value)));
    }
}
