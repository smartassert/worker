<?php

declare(strict_types=1);

namespace App\Model\EventType;

class TestEventType implements TestEventTypeInterface
{
    /**
     * @param EventTypeInterface::TEST_* $value
     */
    public function __construct(
        private readonly string $value,
    ) {}

    public function serialize(): string
    {
        return $this->value;
    }
}
