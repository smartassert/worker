<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;
use App\Model\ResourceReferenceSource;
use webignition\BasilCompilerModels\SuiteManifest;

class SourceCompilationPassedEvent extends AbstractSourceEvent
{
    public function __construct(
        string $source,
        private readonly SuiteManifest $suiteManifest
    ) {
        parent::__construct($source);
    }

    public function getSuiteManifest(): SuiteManifest
    {
        return $this->suiteManifest;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::COMPILATION_PASSED;
    }

    public function getRelatedReferenceSources(): array
    {
        $stepNames = [];
        foreach ($this->suiteManifest->getTestManifests() as $testManifest) {
            $stepNames = array_merge($stepNames, $testManifest->getStepNames());
        }

        $referenceSources = [];
        foreach ($stepNames as $stepName) {
            $referenceSources[] = new ResourceReferenceSource($stepName, [$this->source, $stepName]);
        }

        return $referenceSources;
    }
}
