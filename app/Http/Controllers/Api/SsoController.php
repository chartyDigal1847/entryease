<?php

namespace App\Http\Controllers\Api;

use App\Models\SsoToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * SsoController - Token Exchange & Session Heartbeat
 *
 * Handles the secure token exchange flow between module iframe
 * and portal SSO system.
 *
 * Security Model:
 * - Tokens are single-use and expire after 5 minutes
 * - Portal signature must be validated
 * - Origin validation on all calls
 * - Session created only after token validation
 */
class SsoController extends Controller
{
    /**
     * POST /api/sso/exchange
     *
     * Exchange portal-issued token for authenticated session.
     *
     * Flow:
     * 1. Module frontend receives token from portal (via postMessage)
     * 2. Module frontend calls this endpoint with token
     * 3. Endpoint validates token (signature, expiration, single-use)
     * 4. Endpoint creates session with SSO context
     * 5. Endpoint returns user identity + session status
     * 6. Module frontend receives module:ready event
     * 7. Module UI initializes
     *
     * Request:
     * ```json
     * {
     *   "token": "portal-issued-single-use-token",
     *   "embedded": true
     * }
     * ```
     *
     * Response (200):
     * ```json
     * {
     *   "success": true,
     *   "user": {
     *     "id": "user-sso-id",
     *     "name": "User Name",
     *     "email": "user@example.com",
     *     "role": "student"
     *   },
     *   "embedded": true
     * }
     * ```
     *
     * Errors:
     * - 400: Invalid token format
     * - 401: Token invalid, expired, or already exchanged
     * - 403: Portal signature validation failed
     */
    public function exchange(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'token' => 'required|string|max:255',
                'embedded' => 'sometimes|boolean',
            ]);

            $tokenString = $validated['token'];
            $embedded = $validated['embedded'] ?? false;

            \Log::info('[SSO] Token exchange attempt', [
                'token' => substr($tokenString, 0, 8) . '...',
                'embedded' => $embedded,
            ]);

            // Find and validate token
            $token = SsoToken::findValid($tokenString);

            if (!$token) {
                \Log::warning('[SSO] Token exchange failed: invalid or expired', [
                    'token' => substr($tokenString, 0, 8) . '...',
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_token',
                    'message' => 'Token invalid, expired, or already exchanged',
                ], 401);
            }

            // Validate portal signature
            $portalPublicKey = $this->getPortalPublicKey();
            if (!$token->validateSignature($portalPublicKey)) {
                \Log::warning('[SSO] Token exchange failed: invalid signature', [
                    'sso_id' => $token->sso_id,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'signature_invalid',
                    'message' => 'Portal signature validation failed',
                ], 403);
            }

            // Mark token as exchanged (single-use enforcement)
            $token->markExchanged();

            // Normalize role
            $role = $this->normalizeRole($token->sso_role);

            // Create authenticated session
            $request->session()->flush(); // Clear any existing session
            session([
                'sso_id' => $token->sso_id,
                'sso_role' => $role,
                'sso_name' => $token->sso_name,
                'sso_email' => $token->sso_email,
                'sso_embedded' => $embedded,
                'sso_authenticated_at' => now()->timestamp,
            ]);

            \Log::info('[SSO] Token exchange successful', [
                'sso_id' => $token->sso_id,
                'role' => $role,
                'embedded' => $embedded,
            ]);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $token->sso_id,
                    'name' => $token->sso_name,
                    'email' => $token->sso_email,
                    'role' => $role,
                ],
                'embedded' => $embedded,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('[SSO] Token exchange validation error', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Invalid request format',
                'errors' => $e->errors(),
            ], 400);

        } catch (\Exception $e) {
            \Log::error('[SSO] Token exchange error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'exchange_error',
                'message' => 'An error occurred during token exchange',
            ], 500);
        }
    }

    /**
     * GET /api/sso/heartbeat
     *
     * Check if current session is still valid (portal session not expired).
     *
     * Module calls this periodically to detect if portal logged out.
     * If portal session expired, portal will invalidate the token
     * and module should detect logout.
     *
     * Request:
     * ```
     * GET /api/sso/heartbeat
     * ```
     *
     * Response (200 - authenticated):
     * ```json
     * {
     *   "valid": true,
     *   "user": {
     *     "id": "user-sso-id",
     *     "name": "User Name",
     *     "email": "user@example.com",
     *     "role": "student"
     *   }
     * }
     * ```
     *
     * Response (401 - not authenticated):
     * ```json
     * {
     *   "valid": false,
     *   "error": "session_expired",
     *   "message": "Portal session has expired"
     * }
     * ```
     */
    public function heartbeat(Request $request): JsonResponse
    {
        // Check if session has SSO context
        if (!$request->session()->has('sso_id') || !$request->session()->has('sso_role')) {
            return response()->json([
                'valid' => false,
                'error' => 'no_session',
                'message' => 'Not authenticated',
            ], 401);
        }

        // Session is valid
        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $request->session()->get('sso_id'),
                'name' => $request->session()->get('sso_name'),
                'email' => $request->session()->get('sso_email'),
                'role' => $request->session()->get('sso_role'),
            ],
        ], 200);
    }

    /**
     * POST /api/sso/revoke
     *
     * Module notifies that token should be revoked.
     * Called when iframe unloads before token exchange completes.
     *
     * This prevents token replay if another actor obtains the token string.
     */
    public function revoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string|max:255',
            ]);

            $token = SsoToken::where('token', $validated['token'])
                ->whereNull('exchanged_at')
                ->first();

            if ($token) {
                $token->markExchanged(); // Mark as used without creating session
                \Log::info('[SSO] Token revoked by module', [
                    'token' => substr($validated['token'], 0, 8) . '...',
                ]);
            }

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            \Log::warning('[SSO] Token revoke error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'revoke_error',
            ], 500);
        }
    }

    /**
     * Get portal's public key for signature validation.
     *
     * In production, this should:
     * - Fetch from portal's JWKS endpoint
     * - Cache with TTL
     * - Fall back to config value
     *
     * @return string
     */
    protected function getPortalPublicKey(): string
    {
        return config('deoris.portal_public_key') ?? <<<'KEY'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Z3VS5JJcds3xfn/FoN2
...public_key_content...
-----END PUBLIC KEY-----
KEY;
    }

    /**
     * Normalize role from portal to application role.
     *
     * Portal may send different role formats; normalize to app conventions.
     */
    protected function normalizeRole(?string $role): string
    {
        return match ($role) {
            'hr', 'registrar', 'admission_officer' => 'admission_officer',
            'admin' => 'admin',
            default => 'student',
        };
    }
}
