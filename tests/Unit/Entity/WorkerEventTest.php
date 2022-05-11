<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Entity\WorkerEventType;
use PHPUnit\Framework\TestCase;

class WorkerEventTest extends TestCase
{
    public function testHasState(): void
    {
        $workerEvent = WorkerEvent::create(WorkerEventType::COMPILATION_FAILED, 'non-empty reference', []);
        self::assertTrue($workerEvent->hasState(WorkerEventState::AWAITING));
        self::assertFalse($workerEvent->hasState(WorkerEventState::COMPLETE));

        $workerEvent->setState(WorkerEventState::COMPLETE);
        self::assertFalse($workerEvent->hasState(WorkerEventState::AWAITING));
        self::assertTrue($workerEvent->hasState(WorkerEventState::COMPLETE));
    }
}
