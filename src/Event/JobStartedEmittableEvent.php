<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Model\ResourceReferenceSource;

class JobStartedEmittableEvent extends JobEmittableEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string[] $testPaths
     */
    public function __construct(
        string $label,
        array $testPaths,
    ) {
        $relatedReferenceSources = $this->createRelatedReferenceSources($testPaths);

        parent::__construct($label, WorkerEventOutcome::STARTED, ['tests' => $testPaths], [], $relatedReferenceSources);
    }

    /**
     * @param non-empty-string[] $testPaths
     *
     * @return ResourceReferenceSource[]
     */
    private function createRelatedReferenceSources(array $testPaths): array
    {
        $referenceSources = [];

        foreach ($testPaths as $testPath) {
            $referenceSources[] = new ResourceReferenceSource($testPath, [$testPath]);
        }

        return $referenceSources;
    }
}
