<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;

class GenericEventHandler extends AbstractEventHandler
{
    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent
    {
        return $this->create($job, $event, $event->getPayload());
    }
}