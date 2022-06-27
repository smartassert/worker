<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\ResourceReferenceSource;
use webignition\BasilCompilerModels\Model\TestManifestCollection;

class SourceCompilationPassedEvent extends AbstractSourceEvent
{
    public function __construct(
        string $source,
        private readonly TestManifestCollection $testManifestCollection
    ) {
        parent::__construct($source);
    }

    public function getTestManifestCollection(): TestManifestCollection
    {
        return $this->testManifestCollection;
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::COMPILATION;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return WorkerEventOutcome::PASSED;
    }

    public function getRelatedReferenceSources(): array
    {
        $stepNames = [];
        foreach ($this->getTestManifestCollection()->getManifests() as $testManifest) {
            $stepNames = array_unique(array_merge($stepNames, $testManifest->getStepNames()));
        }

        $referenceSources = [];
        foreach ($stepNames as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$this->source, $stepName]);
        }

        return $referenceSources;
    }
}
