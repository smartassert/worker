<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;

class WorkerEventSetup
{
    private WorkerEventScope $scope;
    private WorkerEventOutcome $outcome;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private WorkerEventState $state;

    private WorkerEventReference $reference;

    public function __construct()
    {
        $this->scope = WorkerEventScope::SOURCE_COMPILATION;
        $this->outcome = WorkerEventOutcome::PASSED;
        $this->payload = [];
        $this->state = WorkerEventState::AWAITING;
        $this->reference = new WorkerEventReference('non-empty label', 'non-empty reference');
    }

    public function getScope(): WorkerEventScope
    {
        return $this->scope;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
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

    public function withScope(WorkerEventScope $scope): self
    {
        $new = clone $this;
        $new->scope = $scope;

        return $new;
    }

    public function withOutcome(WorkerEventOutcome $outcome): self
    {
        $new = clone $this;
        $new->outcome = $outcome;

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
