<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Repository\TestRepository;

class ExecutionProgress
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_RUNNING = 'running';
    public const STATE_COMPLETE = 'complete';
    public const STATE_CANCELLED = 'cancelled';

    public const FINISHED_STATES = [
        ExecutionState::COMPLETE,
        ExecutionState::CANCELLED,
    ];

    public function __construct(
        private TestRepository $testRepository
    ) {
    }

    public function get(): ExecutionState
    {
        $hasFailedTests = 0 !== $this->testRepository->count(['state' => TestState::FAILED->value]);
        $hasCancelledTests = 0 !== $this->testRepository->count(['state' => TestState::CANCELLED->value]);

        if ($hasFailedTests || $hasCancelledTests) {
            return ExecutionState::CANCELLED;
        }

        $hasFinishedTests = 0 !== $this->testRepository->count(['state' => TestState::getFinishedValues()]);
        $hasRunningTests = 0 !== $this->testRepository->count(['state' => TestState::RUNNING->value]);
        $hasAwaitingTests = 0 !== $this->testRepository->count(['state' => TestState::AWAITING->value]);

        if ($hasFinishedTests) {
            return $hasAwaitingTests || $hasRunningTests
                ? ExecutionState::RUNNING
                : ExecutionState::COMPLETE;
        }

        return $hasRunningTests ? ExecutionState::RUNNING : ExecutionState::AWAITING;
    }

    /**
     * @param array<ExecutionState> ...$states
     */
    public function is(...$states): bool
    {
        return in_array($this->get(), $states);
    }
}
