<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface JobEventTypeInterface
{
    /**
     * @return EventTypeInterface::JOB_STARTED|EventTypeInterface::JOB_ENDED|EventTypeInterface::JOB_TIMED_OUT
     */
    public function serialize(): string;
}
