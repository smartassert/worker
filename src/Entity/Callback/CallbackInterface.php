<?php

declare(strict_types=1);

namespace App\Entity\Callback;

use App\Entity\EntityInterface;

interface CallbackInterface extends EntityInterface
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_QUEUED = 'queued';
    public const STATE_SENDING = 'sending';
    public const STATE_FAILED = 'failed';
    public const STATE_COMPLETE = 'complete';

    public const TYPE_JOB_STARTED = 'job/started';
    public const TYPE_JOB_TIME_OUT = 'job/timed-out';
    public const TYPE_JOB_COMPLETED = 'job/completed';
    public const TYPE_JOB_FAILED = 'job/failed';
    public const TYPE_JOB_COMPILED = 'job/compiled';
    public const TYPE_COMPILATION_STARTED = 'compilation/started';
    public const TYPE_COMPILATION_PASSED = 'compilation/passed';
    public const TYPE_COMPILATION_FAILED = 'compilation/failed';
    public const TYPE_EXECUTION_STARTED = 'execution/started';
    public const TYPE_EXECUTION_COMPLETED = 'execution/completed';
    public const TYPE_TEST_STARTED = 'test/started';
    public const TYPE_TEST_PASSED = 'test/passed';
    public const TYPE_TEST_FAILED = 'test/failed';
    public const TYPE_STEP_PASSED = 'step/passed';
    public const TYPE_STEP_FAILED = 'step/failed';

    public const TYPE_UNKNOWN = 'unknown';

    public function getEntity(): CallbackEntity;

    /**
     * @return CallbackInterface::STATE_*
     */
    public function getState(): string;

    /**
     * @param CallbackInterface::STATE_* $state
     */
    public function hasState(string $state): bool;

    /**
     * @param CallbackInterface::STATE_* $state
     */
    public function setState(string $state): void;

    public function getType(): string;

    public function getReference(): string;

    /**
     * @return array<mixed>
     */
    public function getPayload(): array;
}
