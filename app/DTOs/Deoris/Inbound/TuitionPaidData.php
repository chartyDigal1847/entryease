<?php

namespace App\DTOs\Deoris\Inbound;

use Illuminate\Support\Facades\Validator;

final class TuitionPaidData
{
    public function __construct(
        public readonly string $studentEmail,
        public readonly string $studentExternalId,
        public readonly string $paymentReference,
        public readonly float $amount,
        public readonly string $paidAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $validated = Validator::make($data, [
            'student_email' => 'required|email',
            'student_external_id' => 'required|string|max:100',
            'payment_reference' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'required|date',
        ])->validate();

        return new self(
            studentEmail: $validated['student_email'],
            studentExternalId: $validated['student_external_id'],
            paymentReference: $validated['payment_reference'],
            amount: (float) $validated['amount'],
            paidAt: (string) $validated['paid_at'],
        );
    }
}
