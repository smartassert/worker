<?php

declare(strict_types=1);

namespace App\Model\EventType;

readonly class SourceCompilationEventType implements SourceCompilationEventTypeInterface
{
    /**
     * @param EventTypeInterface::SOURCE_COMPILATION_* $value
     */
    public function __construct(
        private readonly string $value,
    ) {}

    public function serialize(): string
    {
        return $this->value;
    }
}
