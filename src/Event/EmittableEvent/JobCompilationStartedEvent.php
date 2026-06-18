<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventType;

class JobCompilationStartedEvent extends AbstractEvent implements EmittableEventInterface
{
    public function __construct(string $label)
    {
        parent::__construct($label, WorkerEventType::JOB_COMPILATION_STARTED);
    }
}
