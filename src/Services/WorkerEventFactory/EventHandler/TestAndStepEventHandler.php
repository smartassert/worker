<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\StepEventInterface;
use App\Event\TestEventInterface;

class TestAndStepEventHandler extends AbstractEventHandler
{
    public function handles(EventInterface $event): bool
    {
        return $event instanceof TestEventInterface || $event instanceof StepEventInterface;
    }

    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent
    {
        return $this->create($job, $event, $event->getPayload());
    }
}
