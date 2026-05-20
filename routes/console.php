<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — EntryEase DEORIS Module
|--------------------------------------------------------------------------
*/

// Prune failed jobs older than 7 days
Schedule::command('queue:prune-failed --hours=168')->daily();

// Clean up expired SSO tokens every 10 minutes
Schedule::command('sso:cleanup-tokens --force')->everyTenMinutes();

// Retry stuck outbox events (published = false, attempts < 5) every 5 minutes
Schedule::command('deoris:retry-outbox')->everyFiveMinutes();

// Prune processed event log older than 30 days (keep table lean)
Schedule::command('deoris:prune-events --days=30')->daily()->at('02:00');
