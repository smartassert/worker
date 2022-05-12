<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilationFailedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationFailedEventHandler extends AbstractCompilationEventHandler
{
    public function handles(Event $event): bool
    {
        return $event instanceof SourceCompilationFailedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof SourceCompilationFailedEvent) {
            return $this->create($job, $event, $this->createPayload($event, [
                'output' => $event->getOutput()->getData(),
            ]));
        }

        return null;
    }
}
