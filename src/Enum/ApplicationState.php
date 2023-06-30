<?php

declare(strict_types=1);

namespace App\Enum;

enum ApplicationState: string
{
    case AWAITING_JOB = 'awaiting-job';
    case COMPILING = 'compiling';
    case EXECUTING = 'executing';
    case COMPLETING_EVENT_DELIVERY = 'completing-event-delivery';
    case COMPLETE = 'complete';
    case TIMED_OUT = 'timed-out';

    public static function isEndState(ApplicationState $state): bool
    {
        return in_array($state, [self::COMPLETE, self::TIMED_OUT]);
    }
}
