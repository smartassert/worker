<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\Document\Test as TestDocument;
use App\Model\ResourceReferenceSource;

class TestEvent extends AbstractEvent implements EventInterface
{
    public function __construct(
        private readonly TestEntity $testEntity,
        TestDocument $document,
        WorkerEventOutcome $outcome
    ) {
        parent::__construct(
            $document->getPath(),
            WorkerEventScope::TEST,
            $outcome,
            [
                'source' => $document->getPath(),
                'document' => $document->getData(),
                'step_names' => $testEntity->getStepNames(),
            ],
            [
                $document->getPath(),
            ],
            $this->createRelatedReferenceSources($testEntity, $document)
        );
    }

    public function getTest(): TestEntity
    {
        return $this->testEntity;
    }

    /**
     * @return ResourceReferenceSource[]
     */
    private function createRelatedReferenceSources(TestEntity $testEntity, TestDocument $testDocument): array
    {
        $referenceSources = [];
        foreach ($testEntity->getStepNames() as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$testDocument->getPath(), $stepName]);
        }

        return $referenceSources;
    }
}
