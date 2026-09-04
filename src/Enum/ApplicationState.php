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

    public function isEnd(): bool
    {
        return in_array($this, [self::COMPLETE, self::TIMED_OUT, self::FAILED]);
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
        return self::AWAITING_JOB === $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
