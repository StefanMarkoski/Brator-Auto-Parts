<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddToBasketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // Laravel's built-in rules, not regex. Note there is no `price` here: the price
        // is read from the database, never accepted from the form.
        return [
            'product_id' => ['required', 'string', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function quantity(): int
    {
        return (int) ($this->validated()['quantity'] ?? 1);
    }
}
