<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use Symfony\Contracts\EventDispatcher\Event;

class ExecutionCompletedEvent extends Event implements EventInterface
{
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
        return WorkerEventOutcome::COMPLETED;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::EXECUTION_COMPLETED;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
