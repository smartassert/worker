<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\JobEndState;
use App\Enum\WorkerEventOutcome;

class JobEndedEmittableEvent extends JobEmittableEvent implements EmittableEventInterface
{
    public function __construct(
        string $label,
        JobEndState $jobEndedState,
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
