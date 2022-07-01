<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;

class SourceCompilationStartedEvent extends AbstractSourceEvent
{
    public function __construct(string $source)
    {
        parent::__construct($source, WorkerEventOutcome::STARTED);
    }
}
