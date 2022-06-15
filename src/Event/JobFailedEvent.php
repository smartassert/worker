<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use Symfony\Contracts\EventDispatcher\Event;

class JobFailedEvent extends Event implements EventInterface
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
        return WorkerEventScope::JOB;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return WorkerEventOutcome::FAILED;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_FAILED;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
