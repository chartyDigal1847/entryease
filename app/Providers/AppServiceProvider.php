<?php

namespace App\Providers;

use App\Support\DeorisBroadcast;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ── Broadcasting fallback ─────────────────────────────────────────────
        $connection = (string) config('broadcasting.default');

        if ($connection === 'reverb' && ! class_exists(\Laravel\Reverb\ReverbServiceProvider::class)) {
            config(['broadcasting.default' => 'log']);
        }

        if (in_array($connection, ['reverb', 'pusher'], true) && ! class_exists(\Pusher\Pusher::class)) {
            config(['broadcasting.default' => 'log']);
        }

        if (! DeorisBroadcast::isEnabled() && in_array(config('broadcasting.default'), ['reverb', 'pusher'], true)) {
            config(['broadcasting.default' => 'log']);
        }

        // ── Rate limiters ─────────────────────────────────────────────────────

        // v1 API: 120 req/min per authenticated user (or IP as fallback)
        RateLimiter::for('api', function (Request $request) {
            $key = $request->session()->get('sso_id') ?? $request->ip();
            return Limit::perMinute(120)->by($key);
        });

        // SSO exchange: 10 req/min per IP (anti-brute-force)
        RateLimiter::for('sso', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
