<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\JobEndState;
use App\Enum\WorkerEventOutcome;

class JobEndedEvent extends AbstractJobEvent implements EmittableEventInterface
{
    public function __construct(
        string $label,
        JobEndState $jobEndedState,
        bool $success,
        int $eventCount,
    ) {
        parent::__construct(
            $label,
            WorkerEventOutcome::ENDED,
            [
                'end_state' => $jobEndedState->value,
                'success' => $success,
                'event_count' => $eventCount,
            ],
            [],
            []
        );
    }
}
