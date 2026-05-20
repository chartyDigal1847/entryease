<?php

namespace App\Listeners\Deoris;

use App\Contracts\Deoris\DeorisEventContract;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogDeorisActivity implements ShouldQueue
{
    use InteractsWithQueue;

    public ?string $connection;

    public string $queue;

    public int $tries = 3;

    public function __construct()
    {
        $this->connection = config('queue.default') === 'sync' ? null : config('queue.default');
        $this->queue = config('deoris.redis_queue', 'deoris-events');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(DeorisEventContract $event): void
    {
        ActivityLog::record(
            "DEORIS: {$event->eventName()} [{$event->correlationId()}]",
            'blue',
        );
    }
}
