<?php

declare(strict_types=1);

namespace App\Enum;

enum CompilationState: string implements StateInterface
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case FAILED = 'failed';
    case COMPLETE = 'complete';
    case UNKNOWN = 'unknown';

    public static function isEndState(StateInterface $state): bool
    {
        return in_array($state, [self::COMPLETE, self::FAILED]);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
