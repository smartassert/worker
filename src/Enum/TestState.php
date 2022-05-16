<?php

declare(strict_types=1);

namespace App\Enum;

enum TestState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case FAILED = 'failed';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';

    /**
     * @return array{'cancelled', 'complete', 'failed'}
     */
    public static function getFinishedValues(): array
    {
        return [self::CANCELLED->value, self::COMPLETE->value, self::FAILED->value];
    }

    /**
     * @return array{'awaiting', 'running'}
     */
    public static function getUnfinishedValues(): array
    {
        return [self::AWAITING->value, self::RUNNING->value];
    }
}
