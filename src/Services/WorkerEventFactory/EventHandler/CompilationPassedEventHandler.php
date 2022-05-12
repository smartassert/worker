<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\SourceCompilationPassedEvent;

class CompilationPassedEventHandler extends AbstractCompilationEventHandler
{
    public function handles(EventInterface $event): bool
    {
        return $event instanceof SourceCompilationPassedEvent;
    }

    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent
    {
        if ($event instanceof SourceCompilationPassedEvent) {
            return $this->create($job, $event, $this->createPayload($event));
        }

        return null;
    }
}
