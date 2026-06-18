<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventState;
use App\Enum\WorkerEventType;

class WorkerEventSetup
{
    private WorkerEventOutcome $outcome;
    private WorkerEventType $type;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private WorkerEventState $state;

    private WorkerEventReference $reference;

    public function __construct()
    {
        $this->outcome = WorkerEventOutcome::PASSED;
        $this->type = WorkerEventType::SOURCE_COMPILATION_PASSED;
        $this->payload = [];
        $this->state = WorkerEventState::AWAITING;
        $this->reference = new WorkerEventReference('non-empty label', 'non-empty reference');
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getType(): WorkerEventType
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

    public function withOutcome(WorkerEventOutcome $outcome): self
    {
        $new = clone $this;
        $new->outcome = $outcome;

        return $new;
    }

    public function withType(WorkerEventType $type): self
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
