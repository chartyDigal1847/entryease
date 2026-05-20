<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FederatedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $raw = (string) $this->query('q', '');
        $normalized = preg_replace('/\s+/u', ' ', trim($raw)) ?? '';

        $this->merge([
            'q' => $normalized,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Search query is required.',
            'q.min' => 'Enter at least :min characters.',
            'q.max' => 'Search query may not be greater than :max characters.',
        ];
    }
}
