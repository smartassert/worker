<?php

declare(strict_types=1);

namespace App\Model\EventType;

readonly class JobCompilationEventType implements JobCompilationEventTypeInterface
{
    /**
     * @param EventTypeInterface::JOB_COMPILATION_ENDED|EventTypeInterface::JOB_COMPILATION_STARTED $value
     */
    public function __construct(
        private readonly string $value,
    ) {}

    public function serialize(): string
    {
        return $this->value;
    }
}
