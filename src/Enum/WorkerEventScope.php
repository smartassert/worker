<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerEventScope: string
{
    case JOB = 'job';
    case COMPILATION = 'compilation';
    case EXECUTION = 'execution';
    case TEST = 'test';
    case STEP = 'step';
    case UNKNOWN = 'unknown';
}
