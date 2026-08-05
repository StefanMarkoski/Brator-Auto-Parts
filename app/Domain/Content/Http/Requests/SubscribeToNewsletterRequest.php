<?php

declare(strict_types=1);

namespace App\Domain\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubscribeToNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
