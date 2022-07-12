<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Job;
use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;

class JobStatus implements \JsonSerializable
{
    /**
     * @param string[]     $sourcePaths
     * @param array<mixed> $serializedTests
     */
    public function __construct(
        private readonly Job $job,
        private readonly string $reference,
        private readonly array $sourcePaths,
        private readonly ApplicationState $applicationState,
        private readonly CompilationState $compilationState,
        private readonly ExecutionState $executionState,
        private readonly EventDeliveryState $eventDeliveryState,
        private readonly array $serializedTests,
        private readonly ResourceReferenceCollection $testReferences,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->job->getLabel(),
            'event_delivery_url' => $this->job->getEventDeliveryUrl(),
            'maximum_duration_in_seconds' => $this->job->getMaximumDurationInSeconds(),
            'test_paths' => $this->job->getTestPaths(),
            'reference' => $this->reference,
            'sources' => $this->sourcePaths,
            'application_state' => $this->applicationState->value,
            'compilation_state' => $this->compilationState->value,
            'execution_state' => $this->executionState->value,
            'event_delivery_state' => $this->eventDeliveryState->value,
            'tests' => $this->serializedTests,
            'references' => $this->testReferences->toArray(),
        ];
    }
}
