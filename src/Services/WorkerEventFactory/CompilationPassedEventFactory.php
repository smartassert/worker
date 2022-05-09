<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilation\PassedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationPassedEventFactory extends AbstractCompilationEventFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof PassedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof PassedEvent) {
            return $this->create($job, $event, $this->createPayload($event));
        }

        return null;
    }
}