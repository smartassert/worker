<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Repository\WorkerEventRepository;

class WorkerEventFactory
{
    public function __construct(
        private readonly WorkerEventRepository $workerEventRepository,
    ) {
    }

    public function createForEvent(Job $job, EventInterface $event): WorkerEvent
    {
        $referenceComponents = $event->getReferenceComponents();
        array_unshift($referenceComponents, $job->getLabel());

        return $this->workerEventRepository->create(
            $event->getType(),
            md5(implode('', $referenceComponents)),
            $event->getPayload()
        );
    }
}
