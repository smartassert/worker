<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\StepEventInterface;
use App\Event\TestEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

class TestAndStepEventFactory extends AbstractEventFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof TestEventInterface || $event instanceof StepEventInterface;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof TestEventInterface || $event instanceof StepEventInterface) {
            return $this->create($job, $event, $event->getDocument()->getData());
        }

        return null;
    }
}
