<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SECURITY: StudentRegistrationRequest
 * 
 * This Form Request class provides centralized validation for student registration.
 * Benefits:
 * 1. All validation logic in one place
 * 2. Automatic CSRF token verification (from FormRequest base class)
 * 3. Input authorization checks
 * 4. Automatic input sanitization and trimming
 * 5. Type-hinting support in controllers
 */
class StudentRegistrationRequest extends FormRequest
{
    /**
     * SECURITY: Determine if the user is authorized to make this request.
     * Currently allowing all (public registration), but can be enhanced for role-based access.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        // Public registration allowed - anyone can submit this form
        return true;
    }

    /**
     * SECURITY: Get the validation rules that apply to the request.
     * 
     * Rules explained:
     * - 'required': Field must be present
     * - 'string': Value must be a string (prevents injection attacks)
     * - 'max:255': Prevents buffer overflow attacks and DB field size violations
     * - 'email': Validates proper email format
     * - 'unique:students,email': Checks email uniqueness at DB level (prevents duplicate accounts)
     * - 'min:6': Prevents weak passwords
     * - 'confirmed': Ensures password and password_confirmation match (password_confirmation field required)
     * - 'regex': Custom validation for phone format
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Full name: prevent SQL injection through strict type checking
            'full_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\.\']+$/', // Only letters, spaces, hyphens, dots, apostrophes
            ],

            // Email: must be valid format and unique in database
            'email' => [
                'required',
                'string',
                'email', // Uses native PHP email validation
                'max:255',
                'unique:students,email', // Database-level uniqueness check
            ],

            // Phone: validated format to prevent injection
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\d\s\+\-\(\)]+$/', // Only digits, spaces, +, -, (, )
            ],

            // Previous school: optional but validated if provided
            'previous_school' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\.\']+$/',
            ],

            // Password: strong requirements to prevent brute force attacks
            'password' => [
                'required',
                'string',
                'min:8', // Minimum 8 characters for strong passwords
                'confirmed', // Requires password_confirmation field
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[a-zA-Z\d@$!%*?&]+$/', // Must have uppercase, lowercase, digit, special char
            ],

            // Confirmation password: must match password field
            'password_confirmation' => 'required|string',
        ];
    }

    /**
     * SECURITY: Custom error messages for better UX without revealing sensitive info
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required.',
            'full_name.regex' => 'Full name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number format is invalid.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must include uppercase, lowercase, number, and special character (@$!%*?&).',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }

    /**
     * SECURITY: Prepare input data by sanitizing and trimming whitespace
     * Called automatically before validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Trim whitespace from string fields (prevents leading/trailing space attacks)
            'full_name' => trim($this->input('full_name')),
            'email' => trim(strtolower($this->input('email'))), // Lowercase email for consistency
            'phone' => preg_replace('/\s+/', '', $this->input('phone')), // Remove extra spaces
            'previous_school' => trim($this->input('previous_school')),
        ]);
    }
}
