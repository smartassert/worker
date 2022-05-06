<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\WorkerEvent;
use PHPUnit\Framework\TestCase;

class CallbackEntityTest extends TestCase
{
    public function testHasState(): void
    {
        $callback = WorkerEvent::create(
            WorkerEvent::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        )
        ;
        self::assertTrue($callback->hasState(WorkerEvent::STATE_AWAITING));
        self::assertFalse($callback->hasState(WorkerEvent::STATE_COMPLETE));

        $callback->setState(WorkerEvent::STATE_COMPLETE);
        self::assertFalse($callback->hasState(WorkerEvent::STATE_AWAITING));
        self::assertTrue($callback->hasState(WorkerEvent::STATE_COMPLETE));
    }
}
