<?php

declare(strict_types=1);

namespace App\Model\EventType;

readonly class JobEventType implements JobEventTypeInterface
{
    /**
     * @param EventTypeInterface::JOB_ENDED|EventTypeInterface::JOB_STARTED|EventTypeInterface::JOB_TIMED_OUT $value
     */
    public function __construct(
        private readonly string $value,
    ) {}

    public function serialize(): string
    {
        return $this->value;
    }
}
