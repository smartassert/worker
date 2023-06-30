<?php

declare(strict_types=1);

namespace App\Enum;

enum ExecutionState: string implements StateInterface
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';

    public static function isEndState(StateInterface $state): bool
    {
        return in_array($state, [self::COMPLETE, self::CANCELLED]);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
