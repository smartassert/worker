<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use Symfony\Contracts\EventDispatcher\Event;

class JobCompiledEvent extends Event implements EventInterface
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
        return WorkerEventOutcome::COMPILED;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_COMPILED;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
