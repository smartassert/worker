<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerEventType: string
{
    case JOB_STARTED = 'job/started';
    case JOB_TIME_OUT = 'job/timed-out';
    case JOB_COMPLETED = 'job/completed';
    case JOB_FAILED = 'job/failed';
    case JOB_COMPILED = 'job/compiled';
    case COMPILATION_STARTED = 'compilation/started';
    case COMPILATION_PASSED = 'compilation/passed';
    case COMPILATION_FAILED = 'compilation/failed';
    case EXECUTION_STARTED = 'execution/started';
    case EXECUTION_COMPLETED = 'execution/completed';
    case TEST_STARTED = 'test/started';
    case TEST_PASSED = 'test/passed';
    case TEST_FAILED = 'test/failed';
    case STEP_PASSED = 'step/passed';
    case STEP_FAILED = 'step/failed';
    case UNKNOWN = 'unknown';
}
