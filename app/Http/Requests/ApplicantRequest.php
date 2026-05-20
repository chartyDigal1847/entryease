<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SECURITY: ApplicantRequest
 * 
 * Form request for student application submission with security:
 * 1. CSRF token verification (automatic)
 * 2. Authentication check (must be logged in student)
 * 3. Input validation with strong rules
 * 4. Grade level whitelist validation
 */
class ApplicantRequest extends FormRequest
{
    /**
     * SECURITY: Authorization - only authenticated students can apply
     */
    public function authorize(): bool
    {
        // SECURITY: Verify user has a valid student session
        // In a production system with proper auth, use:
        // return $this->user() !== null;
        return session()->has('student_id');
    }

    /**
     * SECURITY: Validation rules
     */
    public function rules(): array
    {
        return [
            // Grade level: whitelist validation (enum-style)
            'grade_level' => [
                'required',
                'string',
                'in:Kindergarten,Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12',
            ],

            // Documents: optional file uploads would need file validation here
            // Example (not implemented in this basic form):
            // 'transcript' => 'nullable|mimes:pdf,doc,docx|max:5120', // Max 5MB
            // 'certificates' => 'nullable|array|max:5',

            // Additional information: optional and validated
            'additional_info' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * SECURITY: Custom error messages
     */
    public function messages(): array
    {
        return [
            'grade_level.required' => 'Please select a grade level.',
            'grade_level.in' => 'Invalid grade level selected.',
            'additional_info.max' => 'Additional information cannot exceed 1000 characters.',
        ];
    }

    /**
     * SECURITY: Sanitize input before validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'additional_info' => trim($this->input('additional_info')),
        ]);
    }
}
