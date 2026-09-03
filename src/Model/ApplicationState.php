<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\ApplicationState as ApplicationStateEnum;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;

final readonly class ApplicationState implements SerializableApplicationStateInterface
{
    public function __construct(
        private ApplicationStateEnum $applicationState,
        private CompilationState $compilationState,
        private ExecutionState $executionState,
        private EventDeliveryState $eventDeliveryState,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'application' => new SerializedState($this->applicationState)->toArray(),
            'compilation' => new SerializedState($this->compilationState)->toArray(),
            'execution' => new SerializedState($this->executionState)->toArray(),
            'event_delivery' => new SerializedState($this->eventDeliveryState)->toArray(),
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function equals(ApplicationState $comparator): bool
    {
        return $this->toArray() === $comparator->toArray();
    }
}
