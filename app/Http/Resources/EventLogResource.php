<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'event_id'       => $this->event_id,
            'event'          => $this->event,
            'source'         => $this->source,
            'correlation_id' => $this->correlation_id,
            'processed_at'   => $this->processed_at?->toIso8601String(),
            'metadata'       => $this->metadata,
        ];
    }
}
