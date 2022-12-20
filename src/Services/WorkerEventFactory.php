<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EmittableEvent\EmittableEventInterface;

class WorkerEventFactory
{
    public function __construct(
        private readonly WorkerEventReferenceFactory $workerEventReferenceFactory,
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

    public function create(Job $job, EmittableEventInterface $event): WorkerEvent
    {
        $payload = $event->getPayload();
        $relatedReferenceSources = $event->getRelatedReferenceSources();

        $resourceReferenceCollection = null;

        if (!array_key_exists('related_references', $payload) && [] !== $relatedReferenceSources) {
            $resourceReferenceCollection = $this->workerEventReferenceFactory->createCollection(
                $job->label,
                $relatedReferenceSources
            );
        }

        $reference = $this->workerEventReferenceFactory->create(
            $event->getLabel(),
            $this->referenceFactory->create($job->label, $event->getReferenceComponents())
        );

        return new WorkerEvent(
            $event->getScope(),
            $event->getOutcome(),
            $reference,
            $payload,
            $resourceReferenceCollection
        );
    }
}
