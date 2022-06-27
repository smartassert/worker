<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\Document\Test as TestDocument;
use App\Model\ResourceReferenceSource;
use Symfony\Contracts\EventDispatcher\Event;

class TestEvent extends Event implements EventInterface
{
    public function __construct(
        private readonly WorkerEventOutcome $outcome,
        private readonly TestEntity $testEntity,
        private readonly TestDocument $document
    ) {
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::TEST;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getTest(): TestEntity
    {
        return $this->testEntity;
    }

    public function getPayload(): array
    {
        return [
            'source' => $this->document->getPath(),
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
            $referenceSources[] = new ResourceReferenceSource($stepName, [$this->document->getPath(), $stepName]);
        }

        return $referenceSources;
    }
}
