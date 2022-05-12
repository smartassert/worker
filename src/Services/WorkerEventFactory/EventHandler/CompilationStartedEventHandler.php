<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\SourceCompilationStartedEvent;

class CompilationStartedEventHandler extends AbstractCompilationEventHandler
{
    public function handles(EventInterface $event): bool
    {
        return $event instanceof SourceCompilationStartedEvent;
    }

    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent
    {
        if ($event instanceof SourceCompilationStartedEvent) {
            return $this->create($job, $event, $this->createPayload($event));
        }

        return null;
    }
}
