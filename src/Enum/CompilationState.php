<?php

declare(strict_types=1);

namespace App\Enum;

enum CompilationState: string implements StateInterface
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case FAILED = 'failed';
    case COMPLETE = 'complete';

    public function isEnd(): bool
    {
        return in_array($this, [self::COMPLETE, self::FAILED]);
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
}
