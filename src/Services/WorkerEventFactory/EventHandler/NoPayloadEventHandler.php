<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;

class NoPayloadEventHandler extends AbstractEventHandler
{
    public function handles(EventInterface $event): bool
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

    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent
    {
        if ($this->handles($event)) {
            return $this->create($job, $event, []);
        }

        return null;
    }
}
