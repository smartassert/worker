<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Model\ResourceReferenceSource;

class JobStartedEvent extends JobEvent implements EventInterface
{
    /**
     * @param non-empty-string[] $testPaths
     */
    public function __construct(
        string $label,
        private readonly array $testPaths,
    ) {
        parent::__construct($label, WorkerEventOutcome::STARTED);
    }

    public function getPayload(): array
    {
        return [
            'tests' => $this->testPaths,
        ];
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
