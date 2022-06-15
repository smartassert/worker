<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Model\ResourceReferenceSource;

interface EventInterface
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

    public function getScope(): WorkerEventScope;

    public function getOutcome(): WorkerEventOutcome;

    /**
     * @return ResourceReferenceSource[]
     */
    public function getRelatedReferenceSources(): array;
}
