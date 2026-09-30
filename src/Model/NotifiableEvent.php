<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\WorkerEvent;
use SmartAssert\ResultsClient\Model\EventInterface;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollection;

readonly class NotifiableEvent implements EventInterface
{
    /**
     * @param non-empty-string $job
     */
    public function __construct(
        private string $job,
        private WorkerEvent $eventEntity,
    ) {}

    public function toArray(): array
    {
        $data = array_merge(
            [
                'job' => $this->job,
                'sequence_number' => $this->eventEntity->getId(),
                'type' => $this->eventEntity->getType(),
                'body' => $this->eventEntity->payload,
            ],
            $this->eventEntity->reference->toArray(),
        );

        $references = [];
        foreach ($this->eventEntity->getRelatedReferences() as $reference) {
            $references[] = $reference;
        }

        if (0 !== count($references)) {
            $data['related_references'] = new ResourceReferenceCollection($references)->toArray();
        }

        return $data;
    }
}
