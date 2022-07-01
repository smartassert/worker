<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\Document\Step;

class StepEvent extends AbstractEvent implements EventInterface
{
    public function __construct(
        private readonly Test $test,
        Step $step,
        string $path,
        WorkerEventOutcome $outcome
    ) {
        parent::__construct(
            $step->getName(),
            WorkerEventScope::STEP,
            $outcome,
            [
                'source' => $path,
                'document' => $step->getData(),
                'name' => $step->getName(),
            ],
            [
                $path,
                $step->getName(),
            ]
        );
    }

    public function getTest(): Test
    {
        return $this->test;
    }
}
