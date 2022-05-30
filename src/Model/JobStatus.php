<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;

class JobStatus implements \JsonSerializable
{
    /**
     * @param array<mixed> $serializedJob
     * @param string[]     $sourcePaths
     * @param array<mixed> $serializedTests
     */
    public function __construct(
        private readonly array $serializedJob,
        private readonly string $reference,
        private readonly array $sourcePaths,
        private readonly CompilationState $compilationState,
        private readonly ExecutionState $executionState,
        private readonly EventDeliveryState $eventDeliveryState,
        private readonly array $serializedTests
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->serializedJob,
            [
                'reference' => $this->reference,
                'sources' => $this->sourcePaths,
                'compilation_state' => $this->compilationState->value,
                'execution_state' => $this->executionState->value,
                'event_delivery_state' => $this->eventDeliveryState->value,
                'tests' => $this->serializedTests,
            ]
        );
    }
}
