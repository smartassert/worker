<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilationStartedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationStartedEventHandler extends AbstractCompilationEventHandler
{
    public function handles(Event $event): bool
    {
        return $event instanceof SourceCompilationStartedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof SourceCompilationStartedEvent) {
            return $this->create($job, $event, $this->createPayload($event));
        }

        return null;
    }
}
