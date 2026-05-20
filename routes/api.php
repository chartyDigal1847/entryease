<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SsoController;
use App\Http\Controllers\Api\V1\ApplicantController;
use App\Http\Controllers\Api\V1\ExamScheduleController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\EventLogController;

/*
|--------------------------------------------------------------------------
| EntryEase API Routes
|--------------------------------------------------------------------------
|
| SSO endpoints (no session required) and versioned REST API (v1).
|
| Security:
|   - /api/sso/*   — public token exchange, no session required
|   - /api/v1/*    — requires valid SSO session (EnsureSsoAuthenticated)
|   - Rate limiting applied per IP on all API routes
|
*/

// ── SSO Token Exchange ────────────────────────────────────────────────────────
Route::prefix('sso')->group(function () {
    /**
     * POST /api/sso/exchange
     * Exchange portal-issued single-use token for authenticated session.
     */
    Route::post('/exchange', [SsoController::class, 'exchange'])
        ->name('sso.exchange')
        ->withoutMiddleware(['auth', 'verified']);

    /**
     * GET /api/sso/heartbeat
     * Check if current session is still valid.
     */
    Route::get('/heartbeat', [SsoController::class, 'heartbeat'])
        ->name('sso.heartbeat')
        ->withoutMiddleware(['verified']);

    /**
     * POST /api/sso/revoke
     * Revoke a token before it's exchanged.
     */
    Route::post('/revoke', [SsoController::class, 'revoke'])
        ->name('sso.revoke')
        ->withoutMiddleware(['auth', 'verified']);
});

// ── Versioned REST API v1 ─────────────────────────────────────────────────────
// All v1 routes require a valid SSO session.
// Rate limited to 120 requests/minute per IP.
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['throttle:api', 'sso.required'])
    ->group(function () {

        /**
         * Applicant Resources
         *
         * GET    /api/v1/applicants          — list (paginated, filterable)
         * POST   /api/v1/applicants          — create
         * GET    /api/v1/applicants/{id}     — show
         * PUT    /api/v1/applicants/{id}     — update status
         * DELETE /api/v1/applicants/{id}     — delete
         */
        Route::apiResource('applicants', ApplicantController::class);

        /**
         * Exam Schedule Resources
         *
         * GET    /api/v1/exam-schedules          — list
         * POST   /api/v1/exam-schedules          — create (officer)
         * GET    /api/v1/exam-schedules/{id}     — show
         * PUT    /api/v1/exam-schedules/{id}     — update (officer)
         * DELETE /api/v1/exam-schedules/{id}     — delete (officer)
         */
        Route::apiResource('exam-schedules', ExamScheduleController::class);

        /**
         * Service Statistics
         *
         * GET /api/v1/stats — aggregate metrics (admin/officer)
         */
        Route::get('stats', StatsController::class)->name('stats');

        /**
         * Event Log
         *
         * GET /api/v1/events/processed — processed inbound events
         * GET /api/v1/events/outbox    — outbound event queue
         */
        Route::prefix('events')->name('events.')->group(function () {
            Route::get('processed', [EventLogController::class, 'processed'])->name('processed');
            Route::get('outbox',    [EventLogController::class, 'outbox'])->name('outbox');
        });
    });
