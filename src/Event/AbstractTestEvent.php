<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Model\Document\Test as TestDocument;
use App\Model\ResourceReferenceSource;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractTestEvent extends Event implements EventInterface
{
    /**
     * @param non-empty-string $source
     */
    public function __construct(
        private readonly string $source,
        private readonly TestEntity $testEntity,
        private readonly TestDocument $document,
    ) {
    }

    public function getTest(): TestEntity
    {
        return $this->testEntity;
    }

    public function getPayload(): array
    {
        return [
            'source' => $this->source,
            'document' => $this->document->getData(),
            'step_names' => $this->testEntity->getStepNames(),
        ];
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->document->getPath(),
        ];
    }

    public function getRelatedReferenceSources(): array
    {
        $referenceSources = [];
        foreach ($this->testEntity->getStepNames() as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$this->source, $stepName]);
        }

        return $referenceSources;
    }
}
