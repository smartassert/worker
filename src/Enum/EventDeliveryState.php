<?php

declare(strict_types=1);

namespace App\Enum;

enum EventDeliveryState: string implements StateInterface
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';

    public function isEnd(): bool
    {
        return self::COMPLETE === $this;
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
