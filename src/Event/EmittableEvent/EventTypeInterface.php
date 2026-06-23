<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

interface EventTypeInterface
{
    public const string COMPILATION_FAILED = 'compilation/failed';
    public const string COMPILATION_PASSED = 'compilation/passed';
    public const string COMPILATION_STARTED = 'compilation/started';
    public const string COMPILATION_TIMED_OUT = 'compilation/timed-out';
    public const string JOB_ENDED = 'job/ended';
    public const string JOB_STARTED = 'job/started';
    public const string JOB_TIMED_OUT = 'job/timed-out';
    public const string LIFECYCLE_COMPILATION_COMPLETED = 'lifecycle/compilation-completed';
    public const string LIFECYCLE_COMPILATION_STARTED = 'lifecycle/compilation-started';
    public const string LIFECYCLE_EXECUTION_COMPLETED = 'lifecycle/execution-completed';
    public const string LIFECYCLE_EXECUTION_STARTED = 'lifecycle/execution-started';
    public const string STEP_EXCEPTION = 'step/exception';
    public const string STEP_FAILED = 'step/failed';
    public const string STEP_PASSED = 'step/passed';
    public const string TEST_EXCEPTION = 'test/exception';
    public const string TEST_FAILED = 'test/failed';
    public const string TEST_PASSED = 'test/passed';
    public const string TEST_STARTED = 'test/started';
    public const string TEST_TIMED_OUT = 'test/timed-out';
}
