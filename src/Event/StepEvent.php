<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\Document\Step;

class StepEvent extends AbstractEvent implements EventInterface
{
    /**
     * @param non-empty-string $path
     * @param non-empty-string $stepName
     */
    public function __construct(
        private readonly Test $test,
        Step $step,
        string $path,
        string $stepName,
        WorkerEventOutcome $outcome
    ) {
        parent::__construct(
            $stepName,
            WorkerEventScope::STEP,
            $outcome,
            [
                'source' => $path,
                'document' => $step->getData(),
                'name' => $stepName,
            ],
            [
                $path,
                $stepName,
            ]
        );
    }

    public function getTest(): Test
    {
        return $this->test;
    }
}
