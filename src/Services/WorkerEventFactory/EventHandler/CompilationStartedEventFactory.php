<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilation\StartedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationStartedEventFactory extends AbstractCompilationEventFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof StartedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof StartedEvent) {
            return $this->create($job, $event, $this->createPayload($event));
        }

        return null;
    }
}
