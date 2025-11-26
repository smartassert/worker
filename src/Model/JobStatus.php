<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Job;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollectionInterface;

class JobStatus implements \JsonSerializable
{
    /**
     * @param string[]     $sourcePaths
     * @param array<mixed> $serializedTests
     * @param int[]        $eventIds
     */
    public function __construct(
        private readonly Job $job,
        private readonly string $reference,
        private readonly array $sourcePaths,
        private readonly array $serializedTests,
        private readonly ResourceReferenceCollectionInterface $testReferences,
        private readonly array $eventIds,
    ) {
    }

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
            'references' => $this->testReferences->toArray(),
            'event_ids' => $this->eventIds,
        ];
    }
}
