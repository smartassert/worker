<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;

/**
 * @phpstan-type SerializedEvent array{
 *      job: non-empty-string,
 *      sequence_number: positive-int,
 *      type: non-empty-string,
 *      body: array<mixed>,
 *      related_references?: array<array{label: non-empty-string, reference: non-empty-string}>,
 *      label: non-empty-string,
 *      reference: non-empty-string
 *  }
 *
 * @phpstan-import-type SerializedWorkerEventReference from WorkerEventReference
 */
readonly class SerializableEvent implements \JsonSerializable
{
    /**
     * @param non-empty-string $job
     */
    public function __construct(
        private string $job,
        private WorkerEvent $eventEntity,
    ) {}

    /**
     * @return SerializedEvent
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return SerializedEvent
     */
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
            $data['related_references'] = $this->serializeRelatedReferences($references);
        }

        return $data;
    }

    /**
     * @param WorkerEventReference[] $references
     *
     * @return SerializedWorkerEventReference[]
     */
    private function serializeRelatedReferences(array $references): array
    {
        $serializedReferences = [];
        foreach ($references as $reference) {
            $serializedReferences[] = $reference->toArray();
        }

        return $serializedReferences;
    }
}
