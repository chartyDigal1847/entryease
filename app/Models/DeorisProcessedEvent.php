<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeorisProcessedEvent extends Model
{
    protected $table = 'deoris_processed_events';

    protected $fillable = [
        'event_id',
        'event',
        'source',
        'correlation_id',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function wasProcessed(string $eventId): bool
    {
        return static::query()->where('event_id', $eventId)->exists();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function markProcessed(
        string $eventId,
        string $event,
        string $source,
        ?string $correlationId = null,
        ?array $metadata = null,
    ): self {
        return static::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'event' => $event,
                'source' => $source,
                'correlation_id' => $correlationId,
                'processed_at' => now(),
                'metadata' => $metadata,
            ]
        );
    }
}
