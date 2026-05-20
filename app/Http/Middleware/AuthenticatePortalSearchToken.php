<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePortalSearchToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('deoris.search_token', '');

        if ($configured === '') {
            return response()->json([
                'message' => 'EntryEase federated search is disabled (set ENTRYEASE_SEARCH_TOKEN in .env).',
                'module' => config('deoris.module_name', 'EntryEase'),
            ], 503);
        }

        $token = (string) $request->bearerToken();

        if ($token === '' || ! hash_equals($configured, $token)) {
            return response()->json([
                'message' => 'Invalid or missing search token.',
            ], 401);
        }

        return $next($request);
    }
}
