<?php

namespace App\Console\Commands;

use App\Services\Integration\PortalEcosystemConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DeorisListenPortalCommand extends Command
{
    protected $signature = 'deoris:listen-portal
                            {--channel= : Redis channel (defaults to deoris.events)}';

    protected $description = 'Listen for ecosystem events from other DEORIS modules (via portal Redis bus)';

    public function handle(PortalEcosystemConsumer $consumer): int
    {
        $channel = $this->option('channel') ?: config('deoris.portal.redis_channel', 'deoris.events');
        $connection = config('deoris.redis_pubsub_connection', 'pubsub');

        $this->info("Listening on Redis [{$connection}] channel: {$channel}");

        Redis::connection($connection)->subscribe([$channel], function (string $message) use ($consumer): void {
            $consumer->handleMessage($message);
        });

        return self::SUCCESS;
    }
}
