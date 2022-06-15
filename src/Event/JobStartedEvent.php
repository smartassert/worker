<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Model\ResourceReferenceSource;
use Symfony\Contracts\EventDispatcher\Event;

class JobStartedEvent extends Event implements EventInterface
{
    /**
     * @param non-empty-string[] $testPaths
     */
    public function __construct(
        private readonly array $testPaths,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'tests' => $this->testPaths,
        ];
    }

    public function getReferenceComponents(): array
    {
        return [];
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::JOB;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return WorkerEventOutcome::STARTED;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_STARTED;
    }

    public function getRelatedReferenceSources(): array
    {
        $referenceSources = [];

        foreach ($this->testPaths as $testPath) {
            $referenceSources[] = new ResourceReferenceSource($testPath, [$testPath]);
        }

        return $referenceSources;
    }
}
