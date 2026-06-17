<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerEventType: string
{
    case JOB_STARTED = 'job/started';
    case JOB_TIMED_OUT = 'job/timed-out';
    case JOB_COMPLETED = 'job/ended';
}
