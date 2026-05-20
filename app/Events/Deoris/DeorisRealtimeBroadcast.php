<?php

namespace App\Events\Deoris;

use App\Contracts\Deoris\DeorisEventContract;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeorisRealtimeBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DeorisEventContract $domainEvent,
    ) {}

    public function broadcastAs(): string
    {
        return $this->domainEvent->eventName();
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $studentId = $this->domainEvent->eventData()['student_id'] ?? null;

        $channels = [new PrivateChannel('entryease.admissions')];

        if ($studentId) {
            $channels[] = new PrivateChannel('entryease.student.'.$studentId);
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->domainEvent->eventData();
    }
}
