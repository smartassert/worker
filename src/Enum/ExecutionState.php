<?php

declare(strict_types=1);

namespace App\Enum;

enum ExecutionState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';

    /**
     * @return ExecutionState[]
     */
    public static function getFinishedStates(): array
    {
        return [self::COMPLETE, self::CANCELLED];
    }
}
