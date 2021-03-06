<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;

class WorkerEventFactory
{
    public function __construct(
        private readonly ResourceReferenceFactory $resourceReferenceFactory,
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

    public function create(Job $job, EventInterface $event): WorkerEvent
    {
        $payload = $event->getPayload();
        $relatedReferenceSources = $event->getRelatedReferenceSources();

        $resourceReferenceCollection = null;

        if (!array_key_exists('related_references', $payload) && [] !== $relatedReferenceSources) {
            $resourceReferenceCollection = $this->resourceReferenceFactory->createCollection(
                $job->getLabel(),
                $relatedReferenceSources
            );
        }

        return new WorkerEvent(
            $event->getScope(),
            $event->getOutcome(),
            $event->getLabel(),
            $this->referenceFactory->create($job->getLabel(), $event->getReferenceComponents()),
            $payload,
            $resourceReferenceCollection
        );
    }
}
