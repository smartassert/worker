<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\SourceCompilation\SourceCompilationPassedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationPassedEventHandler extends AbstractCompilationEventHandler
{
    public function handles(Event $event): bool
    {
        return $event instanceof SourceCompilationPassedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof SourceCompilationPassedEvent) {
            return $this->create($job, $event, $this->createPayload($event));
        }

        return null;
    }
}
