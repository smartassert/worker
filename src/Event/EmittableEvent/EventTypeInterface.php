<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

interface EventTypeInterface
{
    public const string JOB_STARTED = 'job/started';
    public const string JOB_TIMED_OUT = 'job/timed-out';
    public const string JOB_ENDED = 'job/ended';
    public const string LIFECYCLE_COMPILATION_STARTED = 'job-compilation/started';
    public const string JOB_COMPILATION_COMPLETED = 'job-compilation/completed';
    public const string SOURCE_COMPILATION_FAILED = 'source-compilation/failed';
    public const string SOURCE_COMPILATION_PASSED = 'source-compilation/passed';
    public const string SOURCE_COMPILATION_STARTED = 'source-compilation/started';
    public const string SOURCE_COMPILATION_TIMED_OUT = 'source-compilation/timed-out';
    public const string JOB_EXECUTION_STARTED = 'job-execution/started';
    public const string JOB_EXECUTION_COMPLETED = 'job-execution/completed';
    public const string TEST_STARTED = 'test/started';
    public const string TEST_FAILED = 'test/failed';
    public const string TEST_PASSED = 'test/passed';
    public const string TEST_TIMED_OUT = 'test/timed-out';
    public const string TEST_EXCEPTION = 'test/exception';
    public const string STEP_EXCEPTION = 'step/exception';
    public const string STEP_FAILED = 'step/failed';
    public const string STEP_PASSED = 'step/passed';
}
