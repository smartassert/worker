<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;

class SourceCompilationStartedEvent extends AbstractSourceEvent
{
    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::COMPILATION;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return WorkerEventOutcome::STARTED;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::COMPILATION_STARTED;
    }
}
