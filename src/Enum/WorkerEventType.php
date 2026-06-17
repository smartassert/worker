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
}
