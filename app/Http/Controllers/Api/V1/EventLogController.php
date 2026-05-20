<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventLogResource;
use App\Models\DeorisProcessedEvent;
use App\Models\DeorisEventOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Versioned API for DEORIS event log inspection.
 *
 * GET /api/v1/events/processed  — processed inbound events
 * GET /api/v1/events/outbox     — outbound event queue status
 */
class EventLogController extends Controller
{
    public function processed(Request $request): AnonymousResourceCollection
    {
        $this->requirePrivileged();

        $events = DeorisProcessedEvent::query()
            ->when($request->filled('event'), fn($q) => $q->where('event', $request->event))
            ->when($request->filled('source'), fn($q) => $q->where('source', $request->source))
            ->orderByDesc('processed_at')
            ->paginate(min((int) $request->query('per_page', 20), 100))
            ->withQueryString();

        return EventLogResource::collection($events);
    }

    public function outbox(Request $request): JsonResponse
    {
        $this->requirePrivileged();

        $query = DeorisEventOutbox::query()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at');

        $perPage = min((int) $request->query('per_page', 20), 100);
        $events = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $events->map(fn($e) => [
                'id'           => $e->id,
                'event_id'     => $e->event_id,
                'event'        => $e->event,
                'status'       => $e->status,
                'attempts'     => $e->attempts,
                'published_at' => $e->published_at?->toIso8601String(),
                'last_error'   => $e->last_error,
                'created_at'   => $e->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'total'        => $events->total(),
                'per_page'     => $events->perPage(),
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
            ],
        ]);
    }

    private function requirePrivileged(): void
    {
        $role = session('sso_role', 'student');
        if (! in_array($role, ['admin', 'admission_officer', 'registrar', 'hr'], true)) {
            abort(403, 'Insufficient permissions.');
        }
    }
}
