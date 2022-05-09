<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\WorkerEvent;
use PHPUnit\Framework\TestCase;

class WorkerEventTest extends TestCase
{
    public function testHasState(): void
    {
        $workerEvent = WorkerEvent::create(
            WorkerEvent::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        )
        ;
        self::assertTrue($workerEvent->hasState(WorkerEvent::STATE_AWAITING));
        self::assertFalse($workerEvent->hasState(WorkerEvent::STATE_COMPLETE));

        $workerEvent->setState(WorkerEvent::STATE_COMPLETE);
        self::assertFalse($workerEvent->hasState(WorkerEvent::STATE_AWAITING));
        self::assertTrue($workerEvent->hasState(WorkerEvent::STATE_COMPLETE));
    }
}
