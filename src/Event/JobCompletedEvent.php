<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use Symfony\Contracts\EventDispatcher\Event;

class JobCompletedEvent extends Event implements EventInterface
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
        return WorkerEventOutcome::COMPLETED;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
