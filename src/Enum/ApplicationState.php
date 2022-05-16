<?php

declare(strict_types=1);

namespace App\Enum;

enum ApplicationState: string
{
    case AWAITING_JOB = 'awaiting-job';
    case AWAITING_SOURCES = 'awaiting-sources';
    case COMPILING = 'compiling';
    case EXECUTING = 'executing';
    case COMPLETING_EVENT_DELIVERY = 'completing-event-delivery';
    case COMPLETE = 'complete';
    case TIMED_OUT = 'timed-out';
}
