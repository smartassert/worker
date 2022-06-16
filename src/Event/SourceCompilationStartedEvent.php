<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;

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
}
