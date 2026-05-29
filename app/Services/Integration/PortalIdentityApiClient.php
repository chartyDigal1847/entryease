<?php

namespace App\Services\Integration;

use Deoris\Integration\Support\Signature;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Signed HTTP client for DEORIS portal identity APIs (no shared database).
 */
class PortalIdentityApiClient
{
    public function listAdmissionOfficers(string $actingPortalUserId): array
    {
        $response = $this->signedJson('GET', '/api/v1/module/admission-officers', [], $actingPortalUserId);

        return $response->json('data', []);
    }

    public function createAdmissionOfficer(string $actingPortalUserId, array $payload): array
    {
        $response = $this->signedJson('POST', '/api/v1/module/admission-officers', $payload, $actingPortalUserId);

        return $response->json('data', []);
    }

    public function updateAdmissionOfficer(string $actingPortalUserId, int $officerId, array $payload): array
    {
        $response = $this->signedJson('PUT', "/api/v1/module/admission-officers/{$officerId}", $payload, $actingPortalUserId);

        return $response->json('data', []);
    }

    public function deleteAdmissionOfficer(string $actingPortalUserId, int $officerId): void
    {
        $this->signedJson('DELETE', "/api/v1/module/admission-officers/{$officerId}", [], $actingPortalUserId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedJson(string $method, string $path, array $payload, string $actingPortalUserId): Response
    {
        $url = rtrim((string) config('deoris.portal.url'), '/').$path;
        $body = $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce = (string) Str::uuid();
        $secret = (string) config('deoris.portal.event_secret');

        if ($secret === '') {
            throw new \RuntimeException('ENTRYEASE_EVENT_SECRET is not configured.');
        }

        $signature = Signature::sign($body, $secret, $timestamp, $nonce);

        $response = $this->httpClient()
            ->withHeaders([
                'X-DEORIS-Module' => config('deoris.module_name', 'EntryEase'),
                'X-DEORIS-Timestamp' => (string) $timestamp,
                'X-DEORIS-Nonce' => $nonce,
                'X-DEORIS-Signature' => $signature,
                'X-Portal-User-Id' => $actingPortalUserId,
                'Accept' => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->send($method, $url);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();
            throw new \RuntimeException("DEORIS identity API failed ({$response->status()}): {$message}");
        }

        return $response;
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::timeout(15);

        if (! config('deoris.portal.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }
}
