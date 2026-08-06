<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Fitment\Queries\Internal\GetVehiclePickerQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The vehicle cascade, as JSON, for the admin's fitment picker.
 *
 * Separate from the storefront's endpoints on purpose. Those serve the shopper's picker, which
 * stops at "which engines does this sub-model have" and answers in the shape that page needs;
 * this one is staff-only and needs the sub-model level exposed as well. Sharing them would mean
 * one endpoint growing shapes for two callers.
 *
 * The listing rule is deliberately NOT "every vehicle at once". There are 82 here and a real
 * tree is tens of thousands — a single multi-select of every engine variant is unusable, and
 * a staff member looking for one car thinks in the same order a shopper does.
 */
final class VehicleLookupController
{
    public function __construct(private GetVehiclePickerQuery $picker) {}

    public function years(): JsonResponse
    {
        return response()->json($this->picker->years());
    }

    public function makes(Request $request): JsonResponse
    {
        return response()->json($this->picker->makes($this->year($request)));
    }

    public function models(Request $request, int $make): JsonResponse
    {
        return response()->json($this->picker->models($make, $this->year($request)));
    }

    /** The "Sub Model" level — variant names, before an engine narrows it to one row. */
    public function subModels(Request $request, int $model): JsonResponse
    {
        return response()->json($this->picker->variantNames($model, $this->year($request)));
    }

    public function engines(Request $request, int $model): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        return response()->json(
            $this->picker->engines($model, $validated['name'], $this->year($request))
        );
    }

    /**
     * The year filter, or null.
     *
     * Optional throughout: staff adding fitment for a part that fits a model across its whole
     * life should not have to pick a year first. It only ever narrows.
     */
    private function year(Request $request): ?int
    {
        $year = $request->integer('year');

        return $year > 0 ? $year : null;
    }
}
