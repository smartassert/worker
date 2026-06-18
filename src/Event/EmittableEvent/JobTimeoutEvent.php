<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Model\EventType\EventTypeInterface;

class JobTimeoutEvent extends AbstractJobEvent implements EmittableEventInterface
{
    public function __construct(
        string $label,
        private readonly int $jobMaximumDuration
    ) {
        parent::__construct(
            $label,
            EventTypeInterface::JOB_TIMED_OUT,
            [
                'maximum_duration_in_seconds' => $this->jobMaximumDuration,
            ]
        );
    }
}
