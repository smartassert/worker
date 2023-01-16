<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;

class WorkerEventSerializer
{
    /**
     * @return array{
     *     header: array{
     *       sequence_number: int,
     *       type: non-empty-string,
     *       label: non-empty-string,
     *       reference: non-empty-string,
     *       related_references?: array<int, array{label: non-empty-string, reference: non-empty-string}>
     *     },
     *     body: array<mixed>
     * }
     */
    public function serialize(WorkerEvent $event): array
    {
        $header = array_merge(
            [
                'sequence_number' => (int) $event->getId(),
                'type' => $event->scope->value . '/' . $event->outcome->value,
            ],
            $event->reference->toArray()
        );

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
