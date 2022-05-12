<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\WorkerEventType;

class TestFailedEvent extends AbstractTestEvent
{
    public function getType(): WorkerEventType
    {
        return WorkerEventType::TEST_FAILED;
    }
}
