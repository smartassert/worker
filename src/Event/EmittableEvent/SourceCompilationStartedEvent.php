<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventType;

class SourceCompilationStartedEvent extends AbstractSourceEvent
{
    public function __construct(string $source)
    {
        parent::__construct(
            $source,
            WorkerEventOutcome::STARTED,
            WorkerEventType::SOURCE_COMPILATION_STARTED,
        );
    }
}
