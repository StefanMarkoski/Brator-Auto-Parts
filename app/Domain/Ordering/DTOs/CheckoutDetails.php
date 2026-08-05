<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTOs;

final readonly class CheckoutDetails
{
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public string $shippingAddress,
        public ?string $notes = null,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromArray(array $validated): self
    {
        return new self(
            customerName: (string) $validated['customer_name'],
            customerEmail: (string) $validated['customer_email'],
            customerPhone: $validated['customer_phone'] ?? null,
            shippingAddress: (string) $validated['shipping_address'],
            notes: $validated['notes'] ?? null,
        );
    }
}
