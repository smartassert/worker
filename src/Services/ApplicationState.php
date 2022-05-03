<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackInterface;
use App\Services\EntityStore\CallbackStore;
use App\Services\EntityStore\JobStore;
use App\Services\EntityStore\SourceStore;

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
        private JobStore $jobStore,
        private CompilationState $compilationState,
        private ExecutionState $executionState,
        private CallbackState $callbackState,
        private CallbackStore $callbackStore,
        private SourceStore $sourceStore
    ) {
    }

    /**
     * @return ApplicationState::STATE_*
     */
    public function __toString(): string
    {
        if (null === $this->jobStore->get()) {
            return self::STATE_AWAITING_JOB;
        }

        if (0 !== $this->callbackStore->getTypeCount(CallbackInterface::TYPE_JOB_TIME_OUT)) {
            return self::STATE_TIMED_OUT;
        }

        if (false === $this->sourceStore->hasAny()) {
            return self::STATE_AWAITING_SOURCES;
        }

        if (false === $this->compilationState->is(...CompilationState::FINISHED_STATES)) {
            return self::STATE_COMPILING;
        }

        if (false === $this->executionState->is(...ExecutionState::FINISHED_STATES)) {
            return self::STATE_EXECUTING;
        }

        if ($this->callbackState->is(CallbackState::STATE_AWAITING, CallbackState::STATE_RUNNING)) {
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
