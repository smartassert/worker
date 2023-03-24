<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Entity\Test as TestEntity;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\Document\Document;
use App\Model\ResourceReferenceSource;

class TestEvent extends AbstractEvent implements EmittableEventInterface, HasTestInterface
{
    /**
     * @param non-empty-string $path
     */
    public function __construct(
        private readonly TestEntity $testEntity,
        Document $document,
        private readonly string $path,
        WorkerEventOutcome $outcome
    ) {
        parent::__construct(
            $path,
            WorkerEventScope::TEST,
            $outcome,
            [
                'source' => $path,
                'document' => $document->getData(),
                'step_names' => $testEntity->stepNames,
            ],
            [
                $path,
            ],
            $this->createRelatedReferenceSources($testEntity)
        );
    }

    public function getTest(): TestEntity
    {
        return $this->testEntity;
    }

    /**
     * @return ResourceReferenceSource[]
     */
    private function createRelatedReferenceSources(TestEntity $testEntity): array
    {
        $referenceSources = [];
        foreach ($testEntity->stepNames as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$this->path, $stepName]);
        }

        return $referenceSources;
    }
}
