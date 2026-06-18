<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventType;

class SourceCompilationTimedOutEvent extends AbstractSourceEvent
{
    public function __construct(string $source, int $timeoutInSeconds)
    {
        parent::__construct(
            $source,
            WorkerEventType::SOURCE_COMPILATION_TIMED_OUT,
            [
                'timeout' => $timeoutInSeconds,
            ]
        );
    }
}
