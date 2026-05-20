<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SECURITY: AdminLoginRequest
 * 
 * Form request for admin login with enhanced security:
 * 1. CSRF token verification (automatic)
 * 2. Rate limiting ready (apply at route level)
 * 3. Input validation
 * 4. Used only for sensitive admin panel
 */
class AdminLoginRequest extends FormRequest
{
    /**
     * SECURITY: Public access to login form (authorization already enforced)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * SECURITY: Validation rules for admin login
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:6',
            ],
        ];
    }

    /**
     * SECURITY: Sanitize input
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim(strtolower($this->input('email'))),
        ]);
    }

    /**
     * SECURITY: Error messages - generic for security
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}
