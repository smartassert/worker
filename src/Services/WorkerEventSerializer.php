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
        return [
            'job' => $job->getLabel(),
            'sequence_number' => (int) $workerEvent->getId(),
            'type' => $workerEvent->getScope()->value . '/' . $workerEvent->getOutcome()->value,
            'label' => $workerEvent->getLabel(),
            'reference' => $workerEvent->getReference(),
            'payload' => $workerEvent->getPayload(),
        ];
    }
}
