<?php

namespace App\DTOs\Deoris\Inbound;

use Illuminate\Support\Facades\Validator;

final class StudentEnrolledData
{
    public function __construct(
        public readonly string $studentEmail,
        public readonly string $studentExternalId,
        public readonly string $enrollmentReference,
        public readonly string $gradeLevel,
        public readonly string $enrolledAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $validated = Validator::make($data, [
            'student_email' => 'required|email',
            'student_external_id' => 'required|string|max:100',
            'enrollment_reference' => 'required|string|max:100',
            'grade_level' => 'required|string|max:50',
            'enrolled_at' => 'required|date',
        ])->validate();

        return new self(
            studentEmail: $validated['student_email'],
            studentExternalId: $validated['student_external_id'],
            enrollmentReference: $validated['enrollment_reference'],
            gradeLevel: $validated['grade_level'],
            enrolledAt: (string) $validated['enrolled_at'],
        );
    }
}
