<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerEventOutcome: string
{
    case STARTED = 'started';
    case COMPLETED = 'completed';
    case PASSED = 'passed';
    case FAILED = 'failed';
    case COMPILED = 'compiled';
    case TIME_OUT = 'timed-out';
    case UNKNOWN = 'unknown';
    case EXCEPTION = 'exception';
    case ENDED = 'ended';
}
