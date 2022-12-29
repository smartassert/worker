<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;

class JobCompilationEndedEvent extends AbstractEvent implements EmittableEventInterface
{
    public function __construct(string $label)
    {
        parent::__construct($label, WorkerEventScope::JOB_COMPILATION, WorkerEventOutcome::ENDED);
    }
}
