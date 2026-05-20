<?php

namespace App\Console\Commands;

use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Services\Integration\EventBusPublisher;
use App\Services\Integration\EventSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DeorisPublishTestCommand extends Command
{
    protected $signature = 'deoris:publish-test
                            {event=TuitionPaid : Event name to simulate}
                            {--source=AssessPay : Source module}';

    protected $description = 'Publish a test inbound event to the DEORIS bus (for local integration testing)';

    public function handle(EventBusPublisher $publisher, EventSignatureService $signatures): int
    {
        $eventName = $this->argument('event');
        $source = $this->option('source');

        $data = match ($eventName) {
            'TuitionPaid' => [
                'student_email' => 'test.student@deoris.test',
                'student_external_id' => 'ext-'.Str::random(8),
                'payment_reference' => 'PAY-'.Str::upper(Str::random(6)),
                'amount' => 15000.00,
                'paid_at' => now()->toIso8601String(),
            ],
            'MedicalApproved' => [
                'student_email' => 'test.student@deoris.test',
                'student_external_id' => 'ext-'.Str::random(8),
                'clearance_reference' => 'MED-'.Str::upper(Str::random(6)),
                'approved_at' => now()->toIso8601String(),
            ],
            'StudentEnrolled' => [
                'student_email' => 'test.student@deoris.test',
                'student_external_id' => 'ext-'.Str::random(8),
                'enrollment_reference' => 'ENR-'.Str::upper(Str::random(6)),
                'grade_level' => 'Grade 7',
                'enrolled_at' => now()->toIso8601String(),
            ],
            default => [
                'message' => 'Test event from EntryEase',
            ],
        };

        $envelope = new DeorisEventEnvelope(
            event: $eventName,
            version: config('deoris.event_version', '1.0'),
            source: $source,
            timestamp: now()->toIso8601String(),
            correlationId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            data: $data,
        );

        $signed = $envelope->toArray($signatures->sign($envelope, $signatures->secretForModule($source)));

        $connection = config('deoris.redis_connection', 'default');
        $channel = config('deoris.redis_channel', 'deoris:events');

        \Illuminate\Support\Facades\Redis::connection($connection)
            ->publish($channel, json_encode($signed, JSON_THROW_ON_ERROR));

        $this->info("Published test event [{$eventName}] from [{$source}] to channel [{$channel}]");

        return self::SUCCESS;
    }
}
