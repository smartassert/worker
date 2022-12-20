<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
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

    public function getScope(): WorkerEventScope;

    public function getOutcome(): WorkerEventOutcome;

    /**
     * @return ResourceReferenceSource[]
     */
    public function getRelatedReferenceSources(): array;

    /**
     * @return non-empty-string
     */
    public function getLabel(): string;
}
