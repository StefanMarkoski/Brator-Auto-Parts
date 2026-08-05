<?php

declare(strict_types=1);

namespace App\Domain\Fitment\Services;

use Illuminate\Support\Facades\Session;

/**
 * The shopper's chosen vehicle, remembered across pages.
 *
 * Crucially this is a FILTER, not a gate. Stefan was explicit: browsing without
 * picking a car must work completely. So nothing requires a selection, and clearing
 * it restores the full catalogue.
 *
 * Kept in the session rather than the URL so the choice survives navigation without
 * having to be threaded through every link.
 */
final class VehicleSelection
{
    public const SESSION_KEY = 'vehicle_variant_id';

    public const PICKER_KEY = 'vehicle_picker';

    public function current(): ?int
    {
        $value = Session::get(self::SESSION_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    public function set(int $variantId): void
    {
        Session::put(self::SESSION_KEY, $variantId);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::PICKER_KEY);
    }

    /**
     * Partial picker state — year/make/model/sub-model chosen so far.
     *
     * The cascade is server-rendered (each choice reloads with the next level filled)
     * because the theme drives these selects through select2, which replaces the
     * native element. Injecting options client-side would fight it; rendering them
     * server-side leaves the theme's own JS completely untouched.
     *
     * @return array{year: ?int, make: ?int, model: ?int, name: ?string}
     */
    public function picker(): array
    {
        $state = (array) Session::get(self::PICKER_KEY, []);

        return [
            'year' => isset($state['year']) ? (int) $state['year'] : null,
            'make' => isset($state['make']) ? (int) $state['make'] : null,
            'model' => isset($state['model']) ? (int) $state['model'] : null,
            'name' => $state['name'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $state */
    public function rememberPicker(array $state): void
    {
        Session::put(self::PICKER_KEY, array_filter($state, fn ($v) => $v !== null && $v !== ''));
    }
}
