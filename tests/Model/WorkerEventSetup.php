<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventState;
use App\Event\EmittableEvent\EventTypeInterface;

class WorkerEventSetup
{
    /**
     * @var EventTypeInterface::*
     */
    private string $type;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private WorkerEventState $state;

    private WorkerEventReference $reference;

    public function __construct()
    {
        $this->type = EventTypeInterface::SOURCE_COMPILATION_PASSED;
        $this->payload = [];
        $this->state = WorkerEventState::AWAITING;
        $this->reference = new WorkerEventReference('non-empty label', 'non-empty reference');
    }

    /**
     * @return EventTypeInterface::*
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<mixed> $payload
     */
    public function withPayload(array $payload): self
    {
        $new = clone $this;
        $new->payload = $payload;

        return $new;
    }

    public function getState(): WorkerEventState
    {
        return $this->state;
    }

    public function withState(WorkerEventState $state): self
    {
        $new = clone $this;
        $new->state = $state;

        return $new;
    }

    /**
     * @param EventTypeInterface::* $type
     */
    public function withType(string $type): self
    {
        $new = clone $this;
        $new->type = $type;

        return $new;
    }

    public function getReference(): WorkerEventReference
    {
        return $this->reference;
    }

    public function withReference(WorkerEventReference $reference): self
    {
        $new = clone $this;
        $new->reference = $reference;

        return $new;
    }
}
