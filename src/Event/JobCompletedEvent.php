<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;
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

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_COMPLETED;
    }
}
