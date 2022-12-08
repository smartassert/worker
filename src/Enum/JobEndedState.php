<?php

declare(strict_types=1);

namespace App\Enum;

enum JobEndedState: string
{
    case COMPLETE = 'complete';
    case TIMED_OUT = 'timed-out';
    case FAILED_COMPILATION = 'failed-compilation';
    case FAILED_TEST = 'failed-test';
}
