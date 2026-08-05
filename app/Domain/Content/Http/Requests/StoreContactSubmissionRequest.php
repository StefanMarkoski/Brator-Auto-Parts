<?php

declare(strict_types=1);

namespace App\Domain\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // Laravel's built-in rules before any regex — house rule, and `email` here is
        // both stricter and more forgiving than anything hand-rolled.
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->validated(),
            'ip_address' => $this->ip(),
        ];
    }
}
