<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use Symfony\Contracts\EventDispatcher\Event;

class ExecutionEvent extends Event implements EventInterface
{
    public function __construct(
        private readonly WorkerEventOutcome $outcome
    ) {
    }

    public function getPayload(): array
    {
        return [];
    }

    public function getReferenceComponents(): array
    {
        return [];
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::EXECUTION;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
