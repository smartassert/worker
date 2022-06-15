<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Enum\WorkerEventType;

class WorkerEventSetup
{
    private WorkerEventScope $scope;
    private WorkerEventOutcome $outcome;
    private WorkerEventType $type;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private WorkerEventState $state;

    public function __construct()
    {
        $this->scope = WorkerEventScope::COMPILATION;
        $this->outcome = WorkerEventOutcome::FAILED;
        $this->type = WorkerEventType::COMPILATION_FAILED;
        $this->payload = [];
        $this->state = WorkerEventState::AWAITING;
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

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
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

    public function withType(WorkerEventType $type): self
    {
        $new = clone $this;
        $new->type = $type;

        return $new;
    }
}
