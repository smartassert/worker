<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\WorkerEventType;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Repository\WorkerEventRepository;

class ApplicationProgress
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private CompilationProgress $compilationProgress,
        private ExecutionProgress $executionProgress,
        private EventDeliveryProgress $eventDeliveryProgress,
        private WorkerEventRepository $workerEventRepository,
        private SourceRepository $sourceRepository,
    ) {
    }

    public function get(): ApplicationState
    {
        if (null === $this->jobRepository->get()) {
            return ApplicationState::AWAITING_JOB;
        }

        if (0 !== $this->workerEventRepository->getTypeCount(WorkerEventType::JOB_TIME_OUT)) {
            return ApplicationState::TIMED_OUT;
        }

        if (0 === $this->sourceRepository->count([])) {
            return ApplicationState::AWAITING_SOURCES;
        }

        if (false === $this->compilationProgress->is(...CompilationState::getFinishedStates())) {
            return ApplicationState::COMPILING;
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
