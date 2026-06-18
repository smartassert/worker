<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface TestEventTypeInterface extends EventTypeInterface
{
    /**
     * @return EventTypeInterface::TEST_*
     */
    public function serialize(): string;
}
