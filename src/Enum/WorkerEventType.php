<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerEventType: string
{
    case JOB_STARTED = 'job/started';
    case JOB_TIMED_OUT = 'job/timed-out';
    case JOB_COMPLETED = 'job/ended';
    case JOB_COMPILATION_STARTED = 'job/compilation/started';
    case JOB_COMPILATION_ENDED = 'job/compilation/ended';
    case SOURCE_COMPILATION_FAILED = 'source-compilation/failed';
    case SOURCE_COMPILATION_PASSED = 'source-compilation/passed';
    case SOURCE_COMPILATION_STARTED = 'source-compilation/started';
    case SOURCE_COMPILATION_TIMED_OUT = 'source-compilation/timed-out';
    case JOB_EXECUTION_STARTED = 'job/execution/started';
    case JOB_EXECUTION_COMPLETED = 'job/execution/completed';
    case TEST_STARTED = 'test/started';
    case TEST_FAILED = 'test/failed';
    case TEST_PASSED = 'test/passed';
    case TEST_TIMED_OUT = 'test/timed-out';
    case STEP_EXCEPTION = 'step/exception';
    case STEP_FAILED = 'step/failed';
    case STEP_PASSED = 'step/passed';
}
