<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface JobCompilationEventTypeInterface extends EventTypeInterface
{
    /**
     * @return EventTypeInterface::JOB_COMPILATION_ENDED|EventTypeInterface::JOB_COMPILATION_STARTED
     */
    public function serialize(): string;
}
