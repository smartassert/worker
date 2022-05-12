<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Entity\WorkerEventType;
use App\Model\Document\Step;

class StepFailedEvent extends AbstractStepEvent
{
    public function __construct(
        Step $step,
        string $path,
        private readonly Test $test,
    ) {
        parent::__construct($step, $path);
    }

    public function getTest(): Test
    {
        return $this->test;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::STEP_FAILED;
    }
}
