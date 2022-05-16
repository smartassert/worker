<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\CompilationState;
use App\Enum\WorkerEventType;
use App\Repository\WorkerEventRepository;

class CompilationProgress
{
    public function __construct(
        private WorkerEventRepository $workerEventRepository,
        private SourcePathFinder $sourcePathFinder
    ) {
    }

    public function get(): CompilationState
    {
        if (0 !== $this->workerEventRepository->getTypeCount(WorkerEventType::COMPILATION_FAILED)) {
            return CompilationState::FAILED;
        }

        $compiledSources = $this->sourcePathFinder->findCompiledPaths();
        $nextSource = $this->sourcePathFinder->findNextNonCompiledPath();

        if ([] === $compiledSources) {
            return is_string($nextSource)
                ? CompilationState::RUNNING
                : CompilationState::AWAITING;
        }

        return is_string($nextSource)
            ? CompilationState::RUNNING
            : CompilationState::COMPLETE;
    }

    /**
     * @param array<CompilationState> ...$states
     */
    public function is(...$states): bool
    {
        return in_array($this->get(), $states);
    }
}
