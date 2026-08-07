<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Fitment\Actions\SaveVehicleAction;
use App\Domain\Fitment\Enums\FuelType;
use App\Domain\Fitment\Models\VehicleVariant;
use App\Domain\Fitment\Queries\Internal\ListVehiclesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Vehicles — the fitment tree, managed.
 *
 * The screen exists to be demonstrated as much as used: the storefront's Year → Make → Model →
 * Sub Model → Engine picker is a live query over these rows, so adding one here changes all five
 * dropdowns on the next request. The counts on the page are read the same way the picker reads
 * them, which is what makes that claim checkable rather than a promise.
 */
final class VehicleController
{
    /** The oldest year the form accepts. A parts shop is not a museum catalogue. */
    private const YEAR_FLOOR = 1950;

    public function __construct(
        private SaveVehicleAction $vehicles,
        private ListVehiclesQuery $list,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.pages.vehicles', [
            'vehicles' => $this->list->paginate(20, $request->query('q')),
            'search' => (string) $request->query('q', ''),
            'makes' => $this->list->makesWithModels(),
            'counts' => $this->list->pickerCounts(),
            'fuelTypes' => FuelType::cases(),
            // The form's year bounds. Two years ahead, because next season's models are on sale
            // before the year turns — a shop that cannot enter a 2027 car in 2026 is wrong.
            'yearFloor' => self::YEAR_FLOOR,
            'yearCeiling' => (int) date('Y') + 2,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /*
         | The form's "a make that is not listed" option posts the literal "new". Turned into
         | nothing BEFORE validation, or the integer rule below rejects the request outright and
         | staff get "make id must be an integer" for having used the control as intended.
        */
        foreach (['make_id', 'model_id'] as $field) {
            if ($request->input($field) === 'new') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            /*
             | Nullable, both of them, and the action decides. "Choose one or type one" cannot be
             | expressed as required_without without also rejecting the case where the browser
             | posts an empty text box alongside a chosen id — which is what a form with both
             | controls on screen does every time.
            */
            'make_id' => ['nullable', 'integer', 'exists:vehicle_makes,id'],
            'make_name' => ['nullable', 'string', 'max:60'],
            'model_id' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'model_name' => ['nullable', 'string', 'max:60'],

            'name' => ['required', 'string', 'max:120'],
            'engine_code' => ['nullable', 'string', 'max:32'],
            'fuel_type' => ['nullable', Rule::in(array_column(FuelType::cases(), 'value'))],
            'power_kw' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'engine_cc' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'body_type' => ['nullable', 'string', 'max:32'],

            /*
             | Bounded on both sides, because the picker's year list is MIN(first) to MAX(last):
             | one typo of 1066 would hand every shopper a dropdown of a thousand years.
            */
            'year_from' => ['required', 'integer', 'min:'.self::YEAR_FLOOR, 'max:'.((int) date('Y') + 2)],
            'year_to' => ['nullable', 'integer', 'min:'.self::YEAR_FLOOR, 'max:'.((int) date('Y') + 2)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $saved = $this->vehicles->create([
                // Empty strings arrive from the "type a new one" boxes when they were not used.
                'make_id' => $this->id($validated['make_id'] ?? null),
                'make_name' => $validated['make_name'] ?? null,
                'model_id' => $this->id($validated['model_id'] ?? null),
                'model_name' => $validated['model_name'] ?? null,
                'name' => $validated['name'],
                'engine_code' => $validated['engine_code'] ?? null,
                'fuel_type' => $validated['fuel_type'] ?? FuelType::Petrol->value,
                'power_kw' => $validated['power_kw'] ?? null,
                'engine_cc' => $validated['engine_cc'] ?? null,
                'body_type' => $validated['body_type'] ?? null,
                'year_from' => $validated['year_from'],
                'year_to' => $validated['year_to'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (RuntimeException $e) {
            return $this->back()->withInput()->with('error', $e->getMessage());
        }

        /*
         | Says which rows were created and where to go and see it. A make that already existed
         | is reported as reused rather than passed over in silence — that is the slip this
         | wording exists to surface.
        */
        $made = [];

        if ($saved->madeMake) {
            $made[] = "the make {$saved->makeName}";
        }

        if ($saved->madeModel) {
            $made[] = "the model {$saved->modelName}";
        }

        $message = $saved->label().' was added';
        $message .= $made === []
            ? " under {$saved->makeName} {$saved->modelName}, which already existed."
            : ', creating '.implode(' and ', $made).'.';

        /*
         | Back to the list SEARCHED FOR THE MAKE, not to a bare page 1. The list is alphabetical
         | and paginated, so a Tesla added to a catalogue of Audis and Volkswagens landed on the
         | last page — the save worked, said so, and showed the operator nothing. Found by adding
         | one in the browser and looking for it.
        */
        return redirect()->route('admin.vehicles.index', ['q' => $saved->makeName])
            ->with('status', $message)
            ->with('added_variant', $saved->variant->id);
    }

    public function update(Request $request, string $vehicle): RedirectResponse
    {
        $model = VehicleVariant::query()->findOrFail($vehicle);

        // One control per request, decided by which field arrived — the same rule the coupon and
        // What's Hot screens follow, so a row's several buttons cannot overwrite each other.
        $this->vehicles->setActive($model, $request->boolean('is_active'));

        return $this->back()->with('status', $model->is_active
            ? 'That vehicle is back in the shop\'s filter.'
            : 'That vehicle is hidden from the filter. It is not deleted — switch it back on any time.');
    }

    public function destroy(string $vehicle): RedirectResponse
    {
        $model = VehicleVariant::query()->findOrFail($vehicle);
        $label = trim("{$model->name} {$model->engine_code}");

        try {
            $this->vehicles->delete($model);
        } catch (RuntimeException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()->with('status', "{$label} was deleted.");
    }

    private function id(int|string|null $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * Back to the list.
     *
     * Named route, NOT url()->previous(): that reads the Referer header, which the caller
     * controls, and a redirect target taken from a header is how a page on this domain ends up
     * sending somebody to another site.
     */
    private function back(): RedirectResponse
    {
        return redirect()->route('admin.vehicles.index');
    }
}
