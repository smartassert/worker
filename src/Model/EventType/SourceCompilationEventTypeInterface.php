<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface SourceCompilationEventTypeInterface extends EventTypeInterface
{
    /**
     * @return EventTypeInterface::SOURCE_COMPILATION_*
     */
    public function serialize(): string;
}
