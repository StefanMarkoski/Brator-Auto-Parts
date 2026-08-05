<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBasketLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The quantity endpoint had NO validation at all — a posted 9999 against 84 in
     * stock was accepted, and checkout drove stock_quantity to -498. Zero is allowed
     * because the theme's stepper reaching zero means "remove this line".
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    public function quantity(): int
    {
        return (int) $this->validated()['quantity'];
    }
}
