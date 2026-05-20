<?php

namespace App\Services\Integration;

use App\DTOs\Deoris\DeorisEventEnvelope;

class EventSignatureService
{
    public function sign(DeorisEventEnvelope $envelope, ?string $secret = null): string
    {
        $secret = $secret ?? (string) config('deoris.signing_secret');

        return hash_hmac(
            'sha256',
            json_encode($envelope->signingPayload(), JSON_THROW_ON_ERROR),
            $secret,
        );
    }

    public function verify(DeorisEventEnvelope $envelope, ?string $secret = null): bool
    {
        if ($envelope->signature === null) {
            return false;
        }

        $expected = $this->sign($envelope, $secret);

        return hash_equals($expected, $envelope->signature);
    }

    public function secretForModule(string $module): ?string
    {
        return config("deoris.module_secrets.{$module}");
    }
}
