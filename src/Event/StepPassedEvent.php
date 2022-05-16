<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;

class StepPassedEvent extends AbstractStepEvent
{
    public function getType(): WorkerEventType
    {
        return WorkerEventType::STEP_PASSED;
    }
}
