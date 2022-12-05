<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\JobEndedState;
use App\Enum\WorkerEventOutcome;

class JobEndedEvent extends JobEvent implements EventInterface
{
    public function __construct(
        string $label,
        JobEndedState $jobEndedState,
        bool $success
    ) {
        parent::__construct(
            $label,
            WorkerEventOutcome::ENDED,
            [
                'end_state' => $jobEndedState->value,
                'success' => $success,
            ],
            [],
            []
        );
    }
}
