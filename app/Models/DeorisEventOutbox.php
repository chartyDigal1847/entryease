<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeorisEventOutbox extends Model
{
    protected $table = 'deoris_event_outbox';

    protected $fillable = [
        'event_id',
        'event',
        'status',
        'payload',
        'attempts',
        'published_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public function markPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'attempts' => $this->attempts + 1,
            'last_error' => $error,
        ]);
    }
}
