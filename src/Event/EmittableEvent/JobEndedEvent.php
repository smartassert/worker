<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\JobEndState;
use App\Model\EventType\EventTypeInterface;

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
            EventTypeInterface::JOB_ENDED,
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
