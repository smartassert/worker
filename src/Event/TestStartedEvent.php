<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;

class TestStartedEvent extends AbstractTestEvent
{
    public function getType(): WorkerEventType
    {
        return WorkerEventType::TEST_STARTED;
    }
}
