<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\CompilationState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Repository\TestRepository;
use App\Repository\WorkerEventRepository;

class CompilationProgress
{
    public function __construct(
        private WorkerEventRepository $workerEventRepository,
        private SourcePathFinder $sourcePathFinder,
        private TestRepository $testRepository,
    ) {
    }

    public function get(): CompilationState
    {
        $compilationFailedCount = $this->workerEventRepository->getTypeCount(
            WorkerEventScope::COMPILATION,
            WorkerEventOutcome::FAILED
        );

        if (0 !== $compilationFailedCount) {
            return CompilationState::FAILED;
        }

        $testCount = $this->testRepository->count([]);
        $nextSource = $this->sourcePathFinder->findNextNonCompiledPath();

        if (is_string($nextSource)) {
            return CompilationState::RUNNING;
        }

        return 0 === $testCount ? CompilationState::AWAITING : CompilationState::COMPLETE;
    }

    /**
     * @param CompilationState[] $states
     */
    public function is(array $states): bool
    {
        return in_array($this->get(), $states);
    }
}
