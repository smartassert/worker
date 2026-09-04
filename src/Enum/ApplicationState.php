<?php

declare(strict_types=1);

namespace App\Enum;

enum ApplicationState: string implements StateInterface
{
    case AWAITING_JOB = 'awaiting-job';
    case COMPILING = 'compiling';
    case EXECUTING = 'executing';
    case COMPLETING_EVENT_DELIVERY = 'completing-event-delivery';
    case COMPLETE = 'complete';
    case TIMED_OUT = 'timed-out';
    case FAILED = 'failed';

    public function isEndState(): bool
    {
        return in_array($this, [self::COMPLETE, self::TIMED_OUT, self::FAILED]);
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
        return self::AWAITING_JOB === $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
