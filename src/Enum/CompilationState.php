<?php

declare(strict_types=1);

namespace App\Enum;

enum CompilationState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case FAILED = 'failed';
    case COMPLETE = 'complete';
    case UNKNOWN = 'unknown';

    public static function isEndState(CompilationState $state): bool
    {
        return in_array($state, [self::COMPLETE, self::FAILED]);
    }
}
