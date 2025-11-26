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
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly SourcePathFinder $sourcePathFinder,
        private readonly TestRepository $testRepository,
    ) {}

    public function get(): CompilationState
    {
        $compilationFailedCount = $this->workerEventRepository->getTypeCount(
            WorkerEventScope::SOURCE_COMPILATION,
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
}
