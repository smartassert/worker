<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;

class JobTimeoutEvent extends JobEvent implements EventInterface
{
    public function __construct(
        string $label,
        private readonly int $jobMaximumDuration
    ) {
        parent::__construct($label, WorkerEventOutcome::TIME_OUT, [
            'maximum_duration_in_seconds' => $this->jobMaximumDuration,
        ]);
    }
}
