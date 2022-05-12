<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\SourceCompilationFailedEvent;

class CompilationFailedEventHandler extends AbstractEventHandler
{
    public function handles(EventInterface $event): bool
    {
        return $event instanceof SourceCompilationFailedEvent;
    }

    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent
    {
        if ($event instanceof SourceCompilationFailedEvent) {
            return $this->create($job, $event, $event->getPayload());
        }

        return null;
    }
}
