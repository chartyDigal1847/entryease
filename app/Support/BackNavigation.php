<?php

namespace App\Support;

use Illuminate\Http\Request;

class BackNavigation
{
    private const SESSION_KEY = 'ee_nav_history';

    public static function resolve(?string $route = null, mixed $routeParam = null, ?Request $request = null): string
    {
        $request ??= request();

        if ($route) {
            try {
                return route($route, $routeParam);
            } catch (\Throwable $e) {
                // If route generation fails, log and fall back to dashboard
                \Log::warning('[BackNavigation] Failed to generate route', [
                    'route' => $route,
                    'param' => $routeParam,
                    'error' => $e->getMessage(),
                ]);
                return self::defaultDashboard($request);
            }
        }

        // Guard session access — session may not be available during certain middleware flows
        $stack = [];
        if ($request->hasSession()) {
            try {
                $stack = $request->session()->get(self::SESSION_KEY, []);
            } catch (\Throwable $e) {
                \Log::debug('[BackNavigation] session unavailable', ['error' => $e->getMessage()]);
                $stack = [];
            }
        }
        if (is_array($stack) && count($stack) >= 2) {
            $current = $request->fullUrl();
            // Walk backwards from the end of the stack, skipping the current URL
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                $candidate = $stack[$i];
                if (is_string($candidate)
                    && $candidate !== $current
                    && self::isSameHost($candidate, $request)) {
                    return $candidate;
                }
            }
        }

        // Stack didn't have a usable previous page — fall back to role dashboard
        return self::defaultDashboard($request);
    }

    private static function isSameHost(string $url, Request $request): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host === $request->getHost();
    }

    private static function defaultDashboard(Request $request): string
    {
        $role = null;
        if ($request->hasSession()) {
            try { $role = $request->session()->get('sso_role'); } catch (\Throwable $e) { $role = null; }
        }

        try {
            if ($request->routeIs('student.*')) {
                return route('student.dashboard');
            }

            if ($request->routeIs('admin.*') || ($role === 'admin' && $request->routeIs('exam.*'))) {
                return route('admin.dashboard');
            }

            if ($request->routeIs('exam.*', 'registrar.*')) {
                return route('registrar.dashboard');
            }

            return route('student.dashboard');
        } catch (\Throwable $e) {
            \Log::warning('[BackNavigation] Failed to resolve dashboard route', ['error' => $e->getMessage()]);
            return '/';
        }
    }
}
