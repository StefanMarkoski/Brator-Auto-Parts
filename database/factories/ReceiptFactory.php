<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'receipt_number' => 'BR-2026-'.fake()->unique()->numerify('######'),
            'customer_id' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'shipping_address' => fake()->address(),
            'billing_address' => null,
            // Totals are overwritten by the seeder from the actual lines — a receipt
            // whose total disagrees with its lines is worse than no fixture at all.
            'subtotal_minor' => 0,
            'vat_minor' => 0,
            'shipping_minor' => 0,
            'total_minor' => 0,
            'status' => ReceiptStatus::Paid,
            'placed_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
