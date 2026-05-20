<?php

namespace App\DTOs\Deoris\Inbound;

use Illuminate\Support\Facades\Validator;

final class MedicalApprovedData
{
    public function __construct(
        public readonly string $studentEmail,
        public readonly string $studentExternalId,
        public readonly string $clearanceReference,
        public readonly string $approvedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $validated = Validator::make($data, [
            'student_email' => 'required|email',
            'student_external_id' => 'required|string|max:100',
            'clearance_reference' => 'required|string|max:100',
            'approved_at' => 'required|date',
        ])->validate();

        return new self(
            studentEmail: $validated['student_email'],
            studentExternalId: $validated['student_external_id'],
            clearanceReference: $validated['clearance_reference'],
            approvedAt: (string) $validated['approved_at'],
        );
    }
}
