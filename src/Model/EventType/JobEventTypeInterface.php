<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface JobEventTypeInterface extends EventTypeInterface
{
    /**
     * @return EventTypeInterface::JOB_ENDED|EventTypeInterface::JOB_STARTED|EventTypeInterface::JOB_TIMED_OUT
     */
    public function serialize(): string;
}
