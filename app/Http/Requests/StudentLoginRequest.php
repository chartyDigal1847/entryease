<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SECURITY: StudentLoginRequest
 * 
 * Form request for student login with security focus:
 * 1. CSRF token verification (automatic)
 * 2. Rate limiting ready (can be applied via middleware)
 * 3. Input validation to prevent injection
 * 4. No exposure of sensitive error details
 */
class StudentLoginRequest extends FormRequest
{
    /**
     * SECURITY: Authorization check - public login allowed
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * SECURITY: Validation rules for login
     * Note: Password is NOT heavily validated here because users might have set it with old rules.
     * The actual password check happens through Hash::check() in the controller.
     */
    public function rules(): array
    {
        return [
            // Email must be valid format (prevents injection)
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],

            // Password: basic check, actual verification done with Hash::check()
            'password' => [
                'required',
                'string',
                'min:6',
            ],
        ];
    }

    /**
     * SECURITY: Prepare input by sanitizing
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim(strtolower($this->input('email'))),
        ]);
    }

    /**
     * SECURITY: Custom error messages - generic for security
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
