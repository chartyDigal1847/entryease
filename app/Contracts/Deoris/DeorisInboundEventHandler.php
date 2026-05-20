<?php

namespace App\Contracts\Deoris;

use App\DTOs\Deoris\DeorisEventEnvelope;

interface DeorisInboundEventHandler
{
    public function handles(): string;

    public function handle(DeorisEventEnvelope $envelope): void;
}
