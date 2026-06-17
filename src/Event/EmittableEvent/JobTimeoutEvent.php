<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventType;

class JobTimeoutEvent extends AbstractJobEvent implements EmittableEventInterface
{
    public function __construct(
        string $label,
        private readonly int $jobMaximumDuration
    ) {
        parent::__construct(
            $label,
            WorkerEventOutcome::TIME_OUT,
            WorkerEventType::JOB_TIMED_OUT,
            [
                'maximum_duration_in_seconds' => $this->jobMaximumDuration,
            ]
        );
    }
}
