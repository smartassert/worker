<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Model\ResourceReferenceSource;
use webignition\BasilCompilerModels\Model\TestManifestCollection;

class SourceCompilationPassedEvent extends AbstractSourceEmittableEvent
{
    /**
     * @param non-empty-string $source
     */
    public function __construct(
        string $source,
        private readonly TestManifestCollection $testManifestCollection
    ) {
        parent::__construct(
            $source,
            WorkerEventOutcome::PASSED,
            [],
            [],
            $this->createRelatedReferenceSources($testManifestCollection, $source)
        );
    }

    public function getTestManifestCollection(): TestManifestCollection
    {
        return $this->testManifestCollection;
    }

    /**
     * @param non-empty-string $source
     *
     * @return ResourceReferenceSource[]
     */
    private function createRelatedReferenceSources(TestManifestCollection $collection, string $source): array
    {
        $stepNames = [];
        foreach ($collection->getManifests() as $testManifest) {
            $stepNames = array_unique(array_merge($stepNames, $testManifest->getStepNames()));
        }

        $referenceSources = [];
        foreach ($stepNames as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$source, $stepName]);
        }

        return $referenceSources;
    }
}
