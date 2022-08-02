<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;

class WorkerEventSerializer
{
    /**
     * @return array{
     *     header: array{
     *       job: non-empty-string,
     *       sequence_number: int,
     *       type: non-empty-string,
     *       label: non-empty-string,
     *       reference: non-empty-string,
     *       related_references?: array<int, array{label: non-empty-string, reference: non-empty-string}>
     *     },
     *     body: array<mixed>
     * }
     */
    public function serialize(Job $job, WorkerEvent $event): array
    {
        $header = [
            'job' => $job->label,
            'sequence_number' => (int) $event->getId(),
            'type' => $event->scope->value . '/' . $event->outcome->value,
            'label' => $event->label,
            'reference' => $event->reference,
        ];

        $relatedReferences = $event->getRelatedReferences();
        if (0 !== count($relatedReferences)) {
            $header['related_references'] = $relatedReferences->toArray();
        }

        return [
            'header' => $header,
            'body' => $event->payload,
        ];
    }
}
