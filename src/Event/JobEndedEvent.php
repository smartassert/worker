<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\JobEndedState;
use App\Enum\WorkerEventOutcome;

class JobEndedEvent extends JobEvent implements EventInterface
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        string $label,
        JobEndedState $jobEndedState,
        bool $success,
        array $payload,
    ) {
        parent::__construct(
            $label,
            WorkerEventOutcome::ENDED,
            array_merge($payload, [
                'end_state' => $jobEndedState->value,
                'success' => $success,
            ]),
            [],
            []
        );
    }
}
