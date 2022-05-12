<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class JobTimeoutEvent extends Event implements EventInterface
{
    public function __construct(private int $jobMaximumDuration)
    {
    }

    public function getJobMaximumDuration(): int
    {
        return $this->jobMaximumDuration;
    }

    public function getPayload(): array
    {
        return [
            'maximum_duration_in_seconds' => $this->getJobMaximumDuration(),
        ];
    }

    public function getReferenceComponents(): array
    {
        return [];
    }
}
