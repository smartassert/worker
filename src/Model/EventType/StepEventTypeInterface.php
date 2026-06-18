<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface StepEventTypeInterface extends EventTypeInterface
{
    /**
     * @return EventTypeInterface::STEP_*
     */
    public function serialize(): string;
}
