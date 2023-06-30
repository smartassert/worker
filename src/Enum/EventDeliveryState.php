<?php

declare(strict_types=1);

namespace App\Enum;

enum EventDeliveryState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';

    public static function isEndState(EventDeliveryState $state): bool
    {
        return self::COMPLETE === $state;
    }
}
