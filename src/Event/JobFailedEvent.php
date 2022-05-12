<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\WorkerEventType;
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

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_FAILED;
    }
}
