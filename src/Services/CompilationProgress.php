<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\CompilationState;
use App\Enum\WorkerEventType;
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
        if (0 !== $this->workerEventRepository->getTypeCount(WorkerEventType::COMPILATION_FAILED)) {
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
     * @param array<CompilationState> ...$states
     */
    public function is(...$states): bool
    {
        return in_array($this->get(), $states);
    }
}
