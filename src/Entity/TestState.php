<?php

declare(strict_types=1);

namespace App\Entity;

enum TestState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case FAILED = 'failed';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';

    /**
     * @var array<TestState::CANCELLED|TestState::COMPLETE|TestState::FAILED>
     */
    public const FINISHED_STATES = [
        TestState::FAILED,
        TestState::COMPLETE,
        TestState::CANCELLED,
    ];

    /**
     * @var array<TestState::AWAITING|TestState::RUNNING>
     */
    public const UNFINISHED_STATES = [
        TestState::AWAITING,
        TestState::RUNNING,
    ];

    /**
     * @return array<'awaiting'|'cancelled'|'complete'|'failed'|'running'>
     */
    public static function getFinishedValues(): array
    {
        $values = [];
        foreach (self::FINISHED_STATES as $state) {
            $values[] = $state->value;
        }

        return $values;
    }

    /**
     * @return array<'awaiting'|'cancelled'|'complete'|'failed'|'running'>
     */
    public static function getUnfinishedValues(): array
    {
        $values = [];
        foreach (self::UNFINISHED_STATES as $state) {
            $values[] = $state->value;
        }

        return $values;
    }
}
