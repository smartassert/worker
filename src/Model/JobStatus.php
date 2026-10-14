<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Job;
use App\Entity\WorkerEventReference;

class JobStatus implements \JsonSerializable
{
    /**
     * @param string[]               $sourcePaths
     * @param array<mixed>           $serializedTests
     * @param int[]                  $eventIds
     * @param WorkerEventReference[] $testReferences
     */
    public function __construct(
        private readonly Job $job,
        private readonly string $reference,
        private readonly array $sourcePaths,
        private readonly array $serializedTests,
        private readonly array $testReferences,
        private readonly array $eventIds,
    ) {}

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->job->getLabel(),
            'maximum_duration_in_seconds' => $this->job->maximumDurationInSeconds,
            'test_paths' => $this->job->getTestPaths(),
            'reference' => $this->reference,
            'sources' => $this->sourcePaths,
            'tests' => $this->serializedTests,
            'references' => $this->serializeTestReferences(),
            'event_ids' => $this->eventIds,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function serializeTestReferences(): array
    {
        $references = [];

        foreach ($this->testReferences as $reference) {
            $references[] = $reference->toArray();
        }

        return $references;
    }
}
