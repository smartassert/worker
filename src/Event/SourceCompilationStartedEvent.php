<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;

class SourceCompilationStartedEvent extends AbstractSourceEvent
{
    public function getType(): WorkerEventType
    {
        return WorkerEventType::COMPILATION_STARTED;
    }
}
