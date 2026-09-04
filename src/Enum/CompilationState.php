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

    public function isEndState(): bool
    {
        return in_array($this, [self::COMPLETE, self::FAILED]);
    }

    public function isSuccessState(): bool
    {
        return self::COMPLETE === $this;
    }

    public function isFailedState(): bool
    {
        return $this->isEndState() && false === $this->isSuccessState();
    }

    public function isPendingState(): bool
    {
        return self::AWAITING === $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
