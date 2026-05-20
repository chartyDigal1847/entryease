<?php

namespace App\Services\Integration;

use App\DTOs\Deoris\DeorisEventEnvelope;
use Illuminate\Validation\ValidationException;

class TrustedModuleValidator
{
    public function __construct(
        private readonly EventSignatureService $signatures,
    ) {}

    public function validate(DeorisEventEnvelope $envelope): void
    {
        $trusted = config('deoris.trusted_modules', []);

        if (! in_array($envelope->source, $trusted, true)) {
            throw ValidationException::withMessages([
                'source' => ["Untrusted module source: {$envelope->source}"],
            ]);
        }

        $secret = $this->signatures->secretForModule($envelope->source);

        if ($secret && ! $this->signatures->verify($envelope, $secret)) {
            throw ValidationException::withMessages([
                'signature' => ['Invalid event signature.'],
            ]);
        }
    }
}
