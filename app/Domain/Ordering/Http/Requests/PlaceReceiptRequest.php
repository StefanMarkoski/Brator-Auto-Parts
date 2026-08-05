<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Http\Requests;

use App\Domain\Ordering\DTOs\CheckoutDetails;
use Illuminate\Foundation\Http\FormRequest;

final class PlaceReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDTO(): CheckoutDetails
    {
        return CheckoutDetails::fromArray($this->validated());
    }
}
