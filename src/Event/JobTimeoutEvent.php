<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;

class JobTimeoutEvent extends JobEvent implements EventInterface
{
    public function __construct(
        private readonly int $jobMaximumDuration
    ) {
        parent::__construct(WorkerEventOutcome::TIME_OUT);
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
}
