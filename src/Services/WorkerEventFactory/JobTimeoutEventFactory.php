<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\JobTimeoutEvent;
use Symfony\Contracts\EventDispatcher\Event;

class JobTimeoutEventFactory extends AbstractEventFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof JobTimeoutEvent;
    }

    public function createForEvent(Job $job, Event $event): ?WorkerEvent
    {
        if ($event instanceof JobTimeoutEvent) {
            return $this->create($job, $event, [
                'maximum_duration_in_seconds' => $event->getJobMaximumDuration(),
            ]);
        }

        return null;
    }
}
