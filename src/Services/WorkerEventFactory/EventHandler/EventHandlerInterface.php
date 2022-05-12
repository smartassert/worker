<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;

interface EventHandlerInterface
{
    public function handles(EventInterface $event): bool;

    public function createForEvent(Job $job, EventInterface $event): ?WorkerEvent;
}
