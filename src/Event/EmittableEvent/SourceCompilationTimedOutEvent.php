<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Model\EventType\EventTypeInterface;

class SourceCompilationTimedOutEvent extends AbstractSourceEvent
{
    public function __construct(string $source, int $timeoutInSeconds)
    {
        parent::__construct(
            $source,
            EventTypeInterface::SOURCE_COMPILATION_TIMED_OUT,
            [
                'timeout' => $timeoutInSeconds,
            ]
        );
    }
}
