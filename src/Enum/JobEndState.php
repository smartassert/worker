<?php

declare(strict_types=1);

namespace App\Enum;

enum JobEndState: string
{
    case COMPLETE = 'complete';
    case TIMED_OUT = 'timed-out';
    case FAILED_COMPILATION = 'failed/compilation';
    case FAILED_TEST_FAILURE = 'failed/test/failure';
    case FAILED_TEST_EXCEPTION = 'failed/test/exception';
}
