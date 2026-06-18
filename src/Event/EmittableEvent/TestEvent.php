<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Entity\Test as TestEntity;
use App\Model\Document\Document;
use App\Model\ResourceReferenceSource;

class TestEvent extends AbstractEvent implements EmittableEventInterface, HasTestInterface
{
    /**
     * @param non-empty-string           $path
     * @param EventTypeInterface::TEST_* $type
     */
    public function __construct(
        private readonly TestEntity $testEntity,
        Document $document,
        private readonly string $path,
        string $type,
    ) {
        parent::__construct(
            $path,
            $type,
            [
                'source' => $path,
                'document' => $document->getData(),
                'step_names' => $testEntity->getStepNames(),
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
        foreach ($testEntity->getStepNames() as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$this->path, $stepName]);
        }

        return $referenceSources;
    }
}
