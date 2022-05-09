<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilation\FailedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationFailedEventFactory extends AbstractCompilationEventFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof FailedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof FailedEvent) {
            return $this->create($job, $event, $this->createPayload($event, [
                'output' => $event->getOutput()->getData(),
            ]));
        }

        return null;
    }
}