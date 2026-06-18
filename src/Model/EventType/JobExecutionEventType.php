<?php

declare(strict_types=1);

namespace App\Model\EventType;

class JobExecutionEventType implements JobExecutionEventTypeInterface
{
    /**
     * @param EventTypeInterface::JOB_EXECUTION_COMPLETED|EventTypeInterface::JOB_EXECUTION_STARTED $value
     */
    public function __construct(
        private readonly string $value,
    ) {}

    public function serialize(): string
    {
        return $this->value;
    }
}
