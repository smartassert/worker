<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;

class ApplicationProgress
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private CompilationProgress $compilationProgress,
        private ExecutionProgress $executionProgress,
        private EventDeliveryProgress $eventDeliveryProgress,
        private WorkerEventRepository $workerEventRepository,
    ) {
    }

    public function get(): ApplicationState
    {
        if (false === $this->jobRepository->has()) {
            return ApplicationState::AWAITING_JOB;
        }

        $jobTimeoutCount = $this->workerEventRepository->getTypeCount(
            WorkerEventScope::JOB,
            WorkerEventOutcome::TIME_OUT
        );

        if (0 !== $jobTimeoutCount) {
            return ApplicationState::TIMED_OUT;
        }

        if (false === $this->compilationProgress->is(...CompilationState::getFinishedStates())) {
            return ApplicationState::COMPILING;
        }

        if (CompilationState::FAILED === $this->compilationProgress->get()) {
            return ApplicationState::COMPLETE;
        }

        if (false === $this->executionProgress->is(...ExecutionState::getFinishedStates())) {
            return ApplicationState::EXECUTING;
        }

        if ($this->eventDeliveryProgress->is(EventDeliveryState::AWAITING, EventDeliveryState::RUNNING)) {
            return ApplicationState::COMPLETING_EVENT_DELIVERY;
        }

        return ApplicationState::COMPLETE;
    }

    /**
     * @param array<ApplicationState> ...$states
     */
    public function is(...$states): bool
    {
        return in_array($this->get(), $states);
    }
}
