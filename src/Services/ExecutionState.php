<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\TestState;
use App\Repository\TestRepository;

class ExecutionState
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_RUNNING = 'running';
    public const STATE_COMPLETE = 'complete';
    public const STATE_CANCELLED = 'cancelled';

    public const FINISHED_STATES = [
        self::STATE_COMPLETE,
        self::STATE_CANCELLED,
    ];

    public function __construct(
        private TestRepository $testRepository
    ) {
    }

    /**
     * @return ExecutionState::STATE_*
     */
    public function get(): string
    {
        $hasFailedTests = 0 !== $this->testRepository->count(['state' => TestState::FAILED->value]);
        $hasCancelledTests = 0 !== $this->testRepository->count(['state' => TestState::CANCELLED->value]);

        if ($hasFailedTests || $hasCancelledTests) {
            return self::STATE_CANCELLED;
        }

        $hasFinishedTests = 0 !== $this->testRepository->count(['state' => TestState::getFinishedValues()]);
        $hasRunningTests = 0 !== $this->testRepository->count(['state' => TestState::RUNNING->value]);
        $hasAwaitingTests = 0 !== $this->testRepository->count(['state' => TestState::AWAITING->value]);

        if ($hasFinishedTests) {
            return $hasAwaitingTests || $hasRunningTests
                ? self::STATE_RUNNING
                : self::STATE_COMPLETE;
        }

        return $hasRunningTests ? self::STATE_RUNNING : self::STATE_AWAITING;
    }

    /**
     * @param ExecutionState::STATE_* ...$states
     */
    public function is(...$states): bool
    {
        $states = array_filter($states, function ($item) {
            return is_string($item);
        });

        return in_array($this->get(), $states);
    }
}
