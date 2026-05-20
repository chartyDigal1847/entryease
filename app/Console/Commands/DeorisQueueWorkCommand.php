<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeorisQueueWorkCommand extends Command
{
    protected $signature = 'deoris:queue-work
                            {--tries=5 : Number of times to attempt a job}
                            {--timeout=90 : Seconds before job timeout}';

    protected $description = 'Process DEORIS event bus queue jobs (Redis recommended)';

    public function handle(): int
    {
        $queue = config('deoris.redis_queue', 'deoris-events');

        $this->info("Starting queue worker for [{$queue}]...");

        return $this->call('queue:work', [
            '--queue' => $queue,
            '--tries' => (int) $this->option('tries'),
            '--timeout' => (int) $this->option('timeout'),
            '--backoff' => '5,15,45,120,300',
        ]);
    }
}
