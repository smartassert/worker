<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Model\ResourceReferenceSource;
use Symfony\Contracts\EventDispatcher\Event;

class AbstractEvent extends Event implements EmittableEventInterface
{
    /**
     * @param non-empty-string          $label
     * @param array<mixed>              $payload
     * @param string[]                  $referenceComponents
     * @param ResourceReferenceSource[] $relatedReferenceSources
     */
    public function __construct(
        private readonly string $label,
        private readonly WorkerEventScope $scope,
        private readonly WorkerEventOutcome $outcome,
        private readonly WorkerEventType $type,
        private readonly array $payload = [],
        private readonly array $referenceComponents = [],
        private readonly array $relatedReferenceSources = [],
    ) {}

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReferenceComponents(): array
    {
        return $this->referenceComponents;
    }

    public function getScope(): WorkerEventScope
    {
        return $this->scope;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getType(): WorkerEventType
    {
        return $this->type;
    }

    public function getRelatedReferenceSources(): array
    {
        return $this->relatedReferenceSources;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
