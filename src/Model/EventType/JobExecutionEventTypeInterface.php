<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface JobExecutionEventTypeInterface extends EventTypeInterface
{
    /**
     * @return EventTypeInterface::JOB_EXECUTION_COMPLETED|EventTypeInterface::JOB_EXECUTION_STARTED
     */
    public function serialize(): string;
}
