<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilation\PassedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationPassedEventCallbackFactory extends AbstractCompilationEventCallbackFactory
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
