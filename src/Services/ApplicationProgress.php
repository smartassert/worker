<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApplicationState;
use App\Enum\EventDeliveryState;
use App\Event\EmittableEvent\EventTypeInterface;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;

class ApplicationProgress
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly CompilationProgress $compilationProgress,
        private readonly ExecutionProgress $executionProgress,
        private readonly EventDeliveryProgress $eventDeliveryProgress,
        private readonly WorkerEventRepository $workerEventRepository,
    ) {}

    public function get(): ApplicationState
    {
        if (false === $this->jobRepository->has()) {
            return ApplicationState::AWAITING_JOB;
        }

        $jobTimeoutCount = $this->workerEventRepository->getTypeCount(EventTypeInterface::JOB_TIMED_OUT);

        if (0 !== $jobTimeoutCount) {
            return ApplicationState::TIMED_OUT;
        }

        $compilationState = $this->compilationProgress->get();
        if (false === $compilationState->isEndState()) {
            return ApplicationState::COMPILING;
        }

        $executionState = $this->executionProgress->get();

        if (
            $compilationState->isFailedState()
            || $executionState->isFailedState()
        ) {
            return ApplicationState::FAILED;
        }

        if (false === $executionState->isEndState()) {
            return ApplicationState::EXECUTING;
        }

        $eventDeliveryState = $this->eventDeliveryProgress->get();
        if (in_array($eventDeliveryState, [EventDeliveryState::AWAITING, EventDeliveryState::RUNNING])) {
            return ApplicationState::COMPLETING_EVENT_DELIVERY;
        }

        return ApplicationState::COMPLETE;
    }
}
