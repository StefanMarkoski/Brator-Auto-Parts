<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Fitment\Queries\Internal\GetVehiclePickerQuery;
use App\Domain\Fitment\Services\VehicleSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The "shop by my car" picker.
 *
 * The dropdowns cascade, so each level is fetched as the previous one is chosen —
 * shipping 2,000 variants into every page to avoid three small requests would be the
 * wrong trade.
 */
final class VehicleController
{
    public function __construct(
        private GetVehiclePickerQuery $picker,
        private VehicleSelection $selection,
    ) {}

    public function makes(Request $request): JsonResponse
    {
        return response()->json(
            $this->picker->makes($this->year($request))
        );
    }

    public function models(Request $request, int $make): JsonResponse
    {
        return response()->json(
            $this->picker->models($make, $this->year($request))
        );
    }

    public function variants(Request $request, int $model): JsonResponse
    {
        return response()->json(
            $this->picker->variants($model, $this->year($request))
        );
    }

    /**
     * One step of the cascade. Narrowing reloads the page with the next level filled;
     * choosing an engine identifies an actual variant and applies the filter.
     */
    public function pick(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'make' => ['nullable', 'integer', 'exists:vehicle_makes,id'],
            'model' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'vehicle_variant_id' => ['nullable', 'integer', 'exists:vehicle_variants,id'],
        ]);

        // Choosing a level clears everything below it — a Make from a different
        // manufacturer must not leave the previous Model selected.
        $state = [
            'year' => $validated['year'] ?? null,
            'make' => $validated['make'] ?? null,
            'model' => $validated['model'] ?? null,
            'name' => $validated['name'] ?? null,
        ];

        $this->selection->rememberPicker($state);

        if (! empty($validated['vehicle_variant_id'])) {
            $this->selection->set((int) $validated['vehicle_variant_id']);

            return redirect()->to($request->input('redirect_to') ?: route('shop.categories', [], false));
        }

        return back();
    }

    /** Choose a vehicle and land on the catalogue filtered to it. */
    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_variant_id' => ['required', 'integer', 'exists:vehicle_variants,id'],
        ]);

        $this->selection->set((int) $validated['vehicle_variant_id']);

        return redirect()->to($request->input('redirect_to') ?: route('shop.categories', [], false));
    }

    /** Clearing must restore the whole catalogue — the vehicle is a filter, not a gate. */
    public function clear(Request $request): RedirectResponse
    {
        $this->selection->clear();

        return redirect()->to($request->input('redirect_to') ?: route('shop.categories', [], false));
    }

    private function year(Request $request): ?int
    {
        $year = $request->query('year');

        return is_numeric($year) ? (int) $year : null;
    }
}
