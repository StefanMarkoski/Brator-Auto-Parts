<?php

declare(strict_types=1);

namespace App\Domain\Fitment\DTOs;

use App\Domain\Fitment\Models\VehicleVariant;

/**
 * What actually happened when a vehicle was saved.
 *
 * One form can create up to three rows — a make, a model and the vehicle itself — and staff
 * need to be told which, because "Tesla was created" and "Tesla already existed, so the Model 3
 * was added to it" are different facts. Typing a make that already exists is the common slip
 * this exists to make visible, rather than quietly making a second Tesla.
 */
final class SavedVehicle
{
    public function __construct(
        public VehicleVariant $variant,
        public string $makeName,
        public string $modelName,
        public bool $madeMake,
        public bool $madeModel,
    ) {}

    /** "Tesla Model 3 Long Range 2024 - present", the way the shopper's picker will read it. */
    public function label(): string
    {
        return trim("{$this->makeName} {$this->modelName} {$this->variant->name} {$this->variant->engine_code}")
            .' ('.$this->variant->yearRange().')';
    }
}
