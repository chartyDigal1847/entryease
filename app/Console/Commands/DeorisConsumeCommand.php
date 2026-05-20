<?php

namespace App\Console\Commands;

use App\Services\Integration\EventBusConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DeorisConsumeCommand extends Command
{
    protected $signature = 'deoris:consume
                            {--channel= : Redis pub/sub channel (defaults to config)}
                            {--connection= : Redis connection name}';

    protected $description = 'Listen to the DEORIS Redis event bus and process inbound messages';

    public function handle(EventBusConsumer $consumer): int
    {
        $connection = $this->option('connection') ?? config('deoris.redis_connection', 'default');
        $channel = $this->option('channel') ?? config('deoris.redis_channel', 'deoris:events');

        $this->info("Listening on Redis [{$connection}] channel: {$channel}");
        $this->info('Press Ctrl+C to stop.');

        Redis::connection($connection)->subscribe([$channel], function (string $message) use ($consumer) {
            $this->line('<fg=gray>'.now()->toIso8601String().'</> message received');
            $consumer->handleRawMessage($message);
        });

        return self::SUCCESS;
    }
}
