<?php

namespace App\Console\Commands;

use App\Support\DeorisBroadcast;
use Illuminate\Console\Command;

class DeorisDoctorCommand extends Command
{
    protected $signature = 'deoris:doctor';

    protected $description = 'Verify DEORIS portal integration configuration for EntryEase';

    public function handle(): int
    {
        $this->info('EntryEase DEORIS integration check');
        $this->newLine();

        $checks = [
            'Deoris integration package' => class_exists(\Deoris\Integration\EventPublisher::class),
            'Portal URL' => filled(config('deoris.portal.url')),
            'ENTRYEASE_EVENT_SECRET' => filled(config('deoris.portal.event_secret')),
            'Portal publish enabled' => (bool) config('deoris.portal.publish_enabled'),
            'Queue connection' => filled(config('queue.default')),
            'Pusher SDK (optional)' => class_exists(\Pusher\Pusher::class),
            'Realtime broadcast ready' => DeorisBroadcast::isEnabled(),
            'Federated search token (portal bar)' => filled(config('deoris.search_token')),
        ];

        $failed = false;

        foreach ($checks as $label => $ok) {
            $this->line(sprintf('  [%s] %s', $ok ? 'OK' : '!!', $label));
            if (! $ok && in_array($label, ['Deoris integration package', 'ENTRYEASE_EVENT_SECRET'], true)) {
                $failed = true;
            }
        }

        $redisClient = (string) config('database.redis.client');
        if ($redisClient === 'phpredis' && ! extension_loaded('redis')) {
            $this->newLine();
            $this->warn('Redis client will crash: REDIS_CLIENT=phpredis but the PHP redis extension is not loaded.');
            $this->line('  Set REDIS_CLIENT=predis in .env, ensure predis/predis is installed (composer.json), then php artisan config:clear');
        }

        $this->newLine();
        $this->line('Portal ingest URL: '.rtrim((string) config('deoris.portal.url'), '/').'/api/events');
        $this->line('Redis channel: '.config('deoris.portal.redis_channel'));
        $this->line(
            'Portal Redis dual-publish: '.(config('deoris.portal.publish_redis') ? 'ON (ensure duplicate ingest understood)' : 'OFF (HTTP only — recommended)'),
        );

        $deorisPath = dirname(base_path()).DIRECTORY_SEPARATOR.'DEORIS';
        $this->line('DEORIS portal path: '.(is_dir($deorisPath) ? $deorisPath : '(expected sibling folder DEORIS next to this app, e.g. C:\\xampp\\htdocs\\DEORIS)'));

        if ($failed) {
            $this->newLine();
            $this->error('Required checks failed.');
            $this->line('  1. composer update');
            $this->line('  2. Set ENTRYEASE_EVENT_SECRET in .env (same value as sibling DEORIS install, e.g. C:\xampp\htdocs\DEORIS\.env)');
            $this->line('  3. php artisan config:clear');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('EntryEase is configured for DEORIS portal notifications.');
        $this->line('Live test: submit an application, then check the bell on https://deoris.test');

        return self::SUCCESS;
    }
}
