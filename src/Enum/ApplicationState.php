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

    private const array ACTIVE_STATES = [
        self::AWAITING_JOB,
        self::COMPILING,
        self::EXECUTING,
        self::COMPLETING_EVENT_DELIVERY,
    ];

    private const array END_STATES = [
        self::COMPLETE,
        self::TIMED_OUT,
        self::FAILED,
    ];

    public function isEnd(): bool
    {
        return in_array($this, self::END_STATES);
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

    public function getPreviousStates(): array
    {
        if (in_array($this, self::ACTIVE_STATES)) {
            $position = array_search($this, self::ACTIVE_STATES);
            $position = false === $position ? 0 : $position;
            ++$position;

            return array_slice(self::ACTIVE_STATES, 0, $position);
        }

        if ($this->isEnd()) {
            $states = self::ACTIVE_STATES;
            $states[] = $this;

            return $states;
        }

        return [];
    }
}
