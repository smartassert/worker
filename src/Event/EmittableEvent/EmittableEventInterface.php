<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Model\EventType\EventTypeInterface;
use App\Model\ResourceReferenceSource;

interface EmittableEventInterface
{
    /**
     * @return array<mixed>
     */
    public function getPayload(): array;

    /**
     * @return string[]
     */
    public function getReferenceComponents(): array;

    /**
     * @return EventTypeInterface::*
     */
    public function getType(): string;

    /**
     * @return ResourceReferenceSource[]
     */
    public function getRelatedReferenceSources(): array;

    /**
     * @return non-empty-string
     */
    public function getLabel(): string;
}
