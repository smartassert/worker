<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;
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

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_TIME_OUT;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
