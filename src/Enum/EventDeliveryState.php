<?php

declare(strict_types=1);

namespace App\Enum;

enum EventDeliveryState: string implements StateInterface
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case COMPLETE = 'complete';

    public function isEndState(): bool
    {
        return self::COMPLETE === $this;
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
