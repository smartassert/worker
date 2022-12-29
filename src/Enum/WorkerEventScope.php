<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerEventScope: string
{
    case JOB = 'job';
    case JOB_COMPILATION = 'job/compilation';
    case SOURCE_COMPILATION = 'source-compilation';
    case EXECUTION = 'job/execution';
    case TEST = 'test';
    case STEP = 'step';
    case UNKNOWN = 'unknown';
}
