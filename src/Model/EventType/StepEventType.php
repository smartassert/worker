<?php

declare(strict_types=1);

namespace App\Model\EventType;

readonly class StepEventType implements StepEventTypeInterface
{
    /**
     * @param EventTypeInterface::STEP_* $value
     */
    public function __construct(
        private string $value,
    ) {}

    public function serialize(): string
    {
        return $this->value;
    }
}
