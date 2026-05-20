<?php

namespace App\Http\Controllers;

use App\Models\DeorisProcessedEvent;
use App\Services\Integration\EventBusConsumer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventBusController extends Controller
{
    public function inbound(Request $request, EventBusConsumer $consumer): JsonResponse
    {
        $payload = $request->validate([
            'event' => 'required|string|max:100',
            'version' => 'required|string|max:10',
            'source' => 'required|string|max:50',
            'timestamp' => 'required|date',
            'correlation_id' => 'required|string|max:64',
            'event_id' => 'required|uuid',
            'data' => 'required|array',
            'signature' => 'required|string',
        ]);

        $consumer->ingest($payload);

        return response()->json([
            'accepted' => true,
            'event_id' => $payload['event_id'],
        ], 202);
    }

    public function recent(Request $request): JsonResponse
    {
        $events = DeorisProcessedEvent::query()
            ->orderByDesc('processed_at')
            ->limit((int) $request->query('limit', 20))
            ->get(['event_id', 'event', 'source', 'correlation_id', 'processed_at', 'metadata']);

        return response()->json(['events' => $events]);
    }
}
