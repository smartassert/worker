<?php

declare(strict_types=1);

namespace App\Event;

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

    public function getType(): WorkerEventType
    {
        return WorkerEventType::EXECUTION_COMPLETED;
    }

    public function getRelatedReferenceValues(): array
    {
        return [];
    }
}
