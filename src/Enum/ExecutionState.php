<?php

declare(strict_types=1);

namespace App\Enum;

enum ExecutionState: string implements StateInterface
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';

    public function isEnd(): bool
    {
        return in_array($this, [self::COMPLETE, self::CANCELLED]);
    }

    public function isSuccess(): bool
    {
        return self::COMPLETE === $this;
    }

    public function isFailed(): bool
    {
        return $this->isEnd() && false === $this->isSuccess();
    }

    public function isPending(): bool
    {
        return self::AWAITING === $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getPreviousStates(): array
    {
        if (self::AWAITING === $this) {
            return [$this];
        }

        if (self::RUNNING === $this) {
            return [self::AWAITING, $this];
        }

        return [self::AWAITING, self::RUNNING, $this];
    }
}
