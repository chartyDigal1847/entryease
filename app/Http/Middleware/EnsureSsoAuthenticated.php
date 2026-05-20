<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureSsoAuthenticated Middleware
 *
 * Enforces that all requests have valid SSO context.
 *
 * Security Model:
 * - Every request must have been established through portal SSO
 * - Session must contain sso_id, sso_role, sso_email
 * - Prevents traditional Laravel auth bypass
 * - Protects against direct URL access without authentication
 *
 * Flow:
 * 1. Check if request has valid SSO session context
 * 2. If missing, abort with 401 Unauthorized
 * 3. If present, continue to next middleware/controller
 *
 * Allowed exceptions:
 * - /api/sso/exchange - Token exchange endpoint (no session yet)
 * - /api/sso/heartbeat - Session check (may have no session)
 * - /?embedded=1 - Initial page load (module-bridge.js will handle auth)
 * - Static assets (CSS, JS, images)
 */
class EnsureSsoAuthenticated
{
    /**
     * List of routes that don't require SSO authentication.
     * These are handled specially or are public endpoints.
     */
    protected $except = [
        // SSO token exchange - no session yet
        'api/sso/exchange',
        'api/sso/heartbeat',
        // Initial page load - module-bridge will handle auth
        '/',
        // Deprecated SSO redirect (legacy entryease.js flow)
        'sso/redirect',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip auth check for excepted routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // API routes require session
        if ($request->is('api/*')) {
            return $this->validateApiRequest($request, $next);
        }

        // Web routes require session
        if (!$this->hasSsoContext($request)) {
            // Log for debugging
            $sessionId = 'none';
            if ($request->hasSession()) {
                try {
                    $sessionId = $request->session()?->getId() ?? 'none';
                } catch (\RuntimeException $e) {
                    $sessionId = 'error: ' . $e->getMessage();
                }
            }
            \Log::debug('[SSO] No SSO context found', [
                'path' => $request->path(),
                'has_session' => $request->hasSession(),
                'session_id' => $sessionId,
            ]);
            // Redirect to home (module-bridge will handle auth)
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect('/');
        }

        return $next($request);
    }

    /**
     * Check if route should skip SSO authentication.
     */
    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $except) {
            if ($request->is($except) || $request->fullUrlIs(route('home'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate API request has SSO context.
     */
    protected function validateApiRequest(Request $request, Closure $next): Response
    {
        // /api/sso/* endpoints are handled without session requirement
        if ($request->is('api/sso/*')) {
            return $next($request);
        }

        // All other API endpoints require valid SSO session
        if (!$this->hasSsoContext($request)) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'SSO authentication required. No valid session found.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Check if request has valid SSO context in session.
     *
     * @return bool
     */
    protected function hasSsoContext(Request $request): bool
    {
        // Check if session is available
        try {
            $session = $request->session();
        } catch (\RuntimeException $e) {
            // Session not initialized - no SSO context
            return false;
        }

        // Must have all required SSO fields
        return $session->has('sso_id')
            && $session->has('sso_role')
            && $session->has('sso_email')
            && !empty($session->get('sso_id'));
    }
}
