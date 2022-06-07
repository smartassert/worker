<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;

class TestPassedEvent extends TestEvent
{
    public function getType(): WorkerEventType
    {
        return WorkerEventType::TEST_PASSED;
    }
}
