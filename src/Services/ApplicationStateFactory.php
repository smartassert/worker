<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\ApplicationState;

final readonly class ApplicationStateFactory
{
    public function __construct(
        private ApplicationProgress $applicationProgress,
        private CompilationProgress $compilationProgress,
        private ExecutionProgress $executionProgress,
        private EventDeliveryProgress $eventDeliveryProgress,
    ) {}

    public function create(): ApplicationState
    {
        return new ApplicationState(
            $this->applicationProgress->get(),
            $this->compilationProgress->get(),
            $this->executionProgress->get(),
            $this->eventDeliveryProgress->get(),
        );
    }
}
