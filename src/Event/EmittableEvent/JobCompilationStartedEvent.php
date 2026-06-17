<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;

class JobCompilationStartedEvent extends AbstractEvent implements EmittableEventInterface
{
    public function __construct(string $label)
    {
        parent::__construct(
            $label,
            WorkerEventScope::JOB_COMPILATION,
            WorkerEventOutcome::STARTED,
            WorkerEventType::JOB_COMPILATION_STARTED,
        );
    }
}
