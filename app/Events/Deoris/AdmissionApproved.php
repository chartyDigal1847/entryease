<?php

namespace App\Events\Deoris;

use App\Contracts\Deoris\DeorisEventContract;
use App\DTOs\Deoris\ApplicationStatusChangedData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdmissionApproved implements DeorisEventContract
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ApplicationStatusChangedData $data,
        public readonly ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'AdmissionApproved';
    }

    public function eventVersion(): string
    {
        return config('deoris.event_version', '1.0');
    }

    public function sourceModule(): string
    {
        return config('deoris.module_name', 'EntryEase');
    }

    public function eventData(): array
    {
        return $this->data->toArray();
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }
}
