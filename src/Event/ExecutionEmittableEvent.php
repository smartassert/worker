<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;

class ExecutionEmittableEvent extends AbstractEmittableEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string $label
     */
    public function __construct(string $label, WorkerEventOutcome $outcome)
    {
        parent::__construct($label, WorkerEventScope::EXECUTION, $outcome, [], [], []);
    }
}
