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
        $payload = $workerEvent->payload;

        $data = [
            'job' => $job->label,
            'sequence_number' => (int) $workerEvent->getId(),
            'type' => $workerEvent->scope->value . '/' . $workerEvent->outcome->value,
            'label' => $workerEvent->label,
            'reference' => $workerEvent->reference,
        ];

        $relatedReferences = $workerEvent->getRelatedReferences();
        if (0 !== count($relatedReferences)) {
            $payload['related_references'] = $relatedReferences->toArray();
        }

        $data['payload'] = $payload;

        return $data;
    }
}
