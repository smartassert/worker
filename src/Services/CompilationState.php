<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEventType;
use App\Repository\WorkerEventRepository;

class CompilationState
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_RUNNING = 'running';
    public const STATE_FAILED = 'failed';
    public const STATE_COMPLETE = 'complete';
    public const STATE_UNKNOWN = 'unknown';

    public const FINISHED_STATES = [
        self::STATE_COMPLETE,
        self::STATE_FAILED,
    ];

    public function __construct(
        private WorkerEventRepository $workerEventRepository,
        private SourcePathFinder $sourcePathFinder
    ) {
    }

    /**
     * @return CompilationState::STATE_*
     */
    public function get(): string
    {
        if (0 !== $this->workerEventRepository->getTypeCount(WorkerEventType::COMPILATION_FAILED)) {
            return CompilationState::STATE_FAILED;
        }

        $compiledSources = $this->sourcePathFinder->findCompiledPaths();
        $nextSource = $this->sourcePathFinder->findNextNonCompiledPath();

        if ([] === $compiledSources) {
            return is_string($nextSource)
                ? CompilationState::STATE_RUNNING
                : CompilationState::STATE_AWAITING;
        }

        return is_string($nextSource)
            ? CompilationState::STATE_RUNNING
            : CompilationState::STATE_COMPLETE;
    }

    /**
     * @param CompilationState::STATE_* ...$states
     */
    public function is(...$states): bool
    {
        $states = array_filter($states, function ($item) {
            return is_string($item);
        });

        return in_array($this->get(), $states);
    }
}
