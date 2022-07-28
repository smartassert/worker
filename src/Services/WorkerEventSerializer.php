<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;

class WorkerEventSerializer
{
    /**
     * @return array{
     *     job: non-empty-string,
     *     sequence_number: int,
     *     type: non-empty-string,
     *     label: non-empty-string,
     *     reference: non-empty-string,
     *     payload: array<mixed>,
     *     related_references?: array<int, array{label: non-empty-string, reference: non-empty-string}>
     * }
     */
    public function serialize(Job $job, WorkerEvent $workerEvent): array
    {
        $payload = $workerEvent->getPayload();

        $data = [
            'job' => $job->getLabel(),
            'sequence_number' => (int) $workerEvent->getId(),
            'type' => $workerEvent->getScope()->value . '/' . $workerEvent->getOutcome()->value,
            'label' => $workerEvent->getLabel(),
            'reference' => $workerEvent->getReference(),
        ];

        $relatedReferences = $workerEvent->getRelatedReferences();
        if (0 !== count($relatedReferences)) {
            $payload['related_references'] = $relatedReferences->toArray();
        }

        $data['payload'] = $payload;

        return $data;
    }
}
