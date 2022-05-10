<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use Symfony\Contracts\EventDispatcher\Event;

class NoPayloadEventFactory extends AbstractEventFactory
{
    public function handles(Event $event): bool
    {
        return
            $event instanceof JobReadyEvent
            || $event instanceof JobCompiledEvent
            || $event instanceof ExecutionStartedEvent
            || $event instanceof ExecutionCompletedEvent
            || $event instanceof JobCompletedEvent
            || $event instanceof JobFailedEvent
            ;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($this->handles($event)) {
            return $this->create($job, $event, []);
        }

        return null;
    }
}
