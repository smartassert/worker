<?php

declare(strict_types=1);

namespace App\Enum;

enum ExecutionState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';

    public static function isEndState(ExecutionState $state): bool
    {
        return in_array($state, [self::COMPLETE, self::CANCELLED]);
    }
}
