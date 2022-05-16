<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEventType;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Repository\WorkerEventRepository;

class ApplicationProgress
{
    public const STATE_AWAITING_JOB = 'awaiting-job';
    public const STATE_AWAITING_SOURCES = 'awaiting-sources';
    public const STATE_COMPILING = 'compiling';
    public const STATE_EXECUTING = 'executing';
    public const STATE_COMPLETING_EVENT_DELIVERY = 'completing-event-delivery';
    public const STATE_COMPLETE = 'complete';
    public const STATE_TIMED_OUT = 'timed-out';

    public function __construct(
        private readonly JobRepository $jobRepository,
        private CompilationProgress $compilationProgress,
        private ExecutionState $executionState,
        private EventDeliveryState $eventDeliveryState,
        private WorkerEventRepository $workerEventRepository,
        private SourceRepository $sourceRepository,
    ) {
    }

    /**
     * @return ApplicationProgress::STATE_*
     */
    public function get(): string
    {
        if (null === $this->jobRepository->get()) {
            return self::STATE_AWAITING_JOB;
        }

        if (0 !== $this->workerEventRepository->getTypeCount(WorkerEventType::JOB_TIME_OUT)) {
            return self::STATE_TIMED_OUT;
        }

        if (0 === $this->sourceRepository->count([])) {
            return self::STATE_AWAITING_SOURCES;
        }

        if (false === $this->compilationProgress->is(...CompilationProgress::FINISHED_STATES)) {
            return self::STATE_COMPILING;
        }

        if (false === $this->executionState->is(...ExecutionState::FINISHED_STATES)) {
            return self::STATE_EXECUTING;
        }

        if ($this->eventDeliveryState->is(EventDeliveryState::STATE_AWAITING, EventDeliveryState::STATE_RUNNING)) {
            return self::STATE_COMPLETING_EVENT_DELIVERY;
        }

        return self::STATE_COMPLETE;
    }

    /**
     * @param ApplicationProgress::STATE_* ...$states
     */
    public function is(...$states): bool
    {
        $states = array_filter($states, function ($item) {
            return is_string($item);
        });

        return in_array($this->get(), $states);
    }
}
