<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Fitment\Queries\Internal\GetVehiclePickerQuery;
use App\Domain\Fitment\Services\VehicleSelection;
use App\Support\Http\SafeRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Land on the shop with a make pre-chosen, ready to narrow further.
     *
     * The homepage's "shop by make" tiles used to link to /shop?make=bosch, which the shop
     * page ignored entirely — 34 links that looked like filters and were byte-identical to
     * clicking nothing. A make alone cannot filter parts, because fitment is recorded per
     * engine variant; what it CAN do is start the cascade, which is what a shopper
     * clicking a marque actually wants.
     */
    public function byMake(string $slug): RedirectResponse
    {
        $make = DB::table('vehicle_makes')->where('slug', $slug)->first(['id']);

        if ($make === null) {
            return redirect()->route('shop.categories');
        }

        $this->selection->rememberPicker(['make' => (int) $make->id]);

        return redirect()->route('shop.categories');
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

        // Through SafeRedirect: redirect_to is posted by the page, so it is user input, and
        // reflecting it unchecked made this an open redirect — a link on this shop's domain
        // that lands on somebody else's site.
        return redirect()->to(SafeRedirect::path(
            $request->input('redirect_to'),
            route('shop.categories', [], false),
        ));
    }

    /**
     * "Clear all filters".
     *
     * Separate from clear() above only in what it means to a shopper, but it has to exist:
     * every other filter lives in the URL, so a plain link to the bare listing clears them
     * all — EXCEPT the vehicle, which lives in the session. That was the open finding from
     * the frontend review. The sidebar came back with nothing ticked while the results stayed
     * narrowed to the chosen car, and the "Clear all filters" link stayed on screen, so it
     * looked like clicking it had done nothing.
     *
     * The label says "all", so it clears the car too. The basket is untouched: it also lives
     * in the session, and losing somebody's shopping to a filter control would be worse than
     * the bug this fixes.
     */
    public function clearFilters(Request $request): RedirectResponse
    {
        $this->selection->clear();

        return redirect()->to(SafeRedirect::path(
            $request->input('redirect_to'),
            route('shop.categories', [], false),
        ));
    }

    private function year(Request $request): ?int
    {
        $year = $request->query('year');

        return is_numeric($year) ? (int) $year : null;
    }
}
