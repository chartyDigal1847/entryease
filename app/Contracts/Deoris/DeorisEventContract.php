<?php

namespace App\Contracts\Deoris;

interface DeorisEventContract
{
    public function eventName(): string;

    public function eventVersion(): string;

    public function sourceModule(): string;

    /**
     * @return array<string, mixed>
     */
    public function eventData(): array;

    public function correlationId(): ?string;
}
