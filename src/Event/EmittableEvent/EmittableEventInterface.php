<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventType;
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

    public function getType(): WorkerEventType;

    /**
     * @return ResourceReferenceSource[]
     */
    public function getRelatedReferenceSources(): array;

    /**
     * @return non-empty-string
     */
    public function getLabel(): string;
}
