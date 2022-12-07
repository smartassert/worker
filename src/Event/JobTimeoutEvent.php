<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\JobEndedState;

class JobTimeoutEvent extends JobEndedEvent implements EventInterface
{
    public function __construct(
        string $label,
        private readonly int $jobMaximumDuration
    ) {
        parent::__construct(
            $label,
            JobEndedState::TIMED_OUT,
            false,
            [
                'maximum_duration_in_seconds' => $this->jobMaximumDuration,
            ]
        );
    }
}
