<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Repository\WorkerEventRepository;

class ApplicationState implements \Stringable
{
    public const STATE_AWAITING_JOB = 'awaiting-job';
    public const STATE_AWAITING_SOURCES = 'awaiting-sources';
    public const STATE_COMPILING = 'compiling';
    public const STATE_EXECUTING = 'executing';
    public const STATE_COMPLETING_CALLBACKS = 'completing-callbacks';
    public const STATE_COMPLETE = 'complete';
    public const STATE_TIMED_OUT = 'timed-out';

    public function __construct(
        private readonly JobRepository $jobRepository,
        private CompilationState $compilationState,
        private ExecutionState $executionState,
        private WorkerEventState $workerEventState,
        private WorkerEventRepository $workerEventRepository,
        private SourceRepository $sourceRepository,
    ) {
    }

    /**
     * @return ApplicationState::STATE_*
     */
    public function __toString(): string
    {
        if (null === $this->jobRepository->get()) {
            return self::STATE_AWAITING_JOB;
        }

        if (0 !== $this->workerEventRepository->getTypeCount(WorkerEvent::TYPE_JOB_TIME_OUT)) {
            return self::STATE_TIMED_OUT;
        }

        if (0 === $this->sourceRepository->count([])) {
            return self::STATE_AWAITING_SOURCES;
        }

        if (false === $this->compilationState->is(...CompilationState::FINISHED_STATES)) {
            return self::STATE_COMPILING;
        }

        if (false === $this->executionState->is(...ExecutionState::FINISHED_STATES)) {
            return self::STATE_EXECUTING;
        }

        if ($this->workerEventState->is(WorkerEventState::STATE_AWAITING, WorkerEventState::STATE_RUNNING)) {
            return self::STATE_COMPLETING_CALLBACKS;
        }

        return self::STATE_COMPLETE;
    }

    /**
     * @param ApplicationState::STATE_* ...$states
     */
    public function is(...$states): bool
    {
        $states = array_filter($states, function ($item) {
            return is_string($item);
        });

        return in_array((string) $this, $states);
    }
}
