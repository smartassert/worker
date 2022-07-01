<?php

declare(strict_types=1);

namespace App\Tests\Model;

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

    /**
     * @var non-empty-string
     */
    private string $label;

    public function __construct()
    {
        $this->scope = WorkerEventScope::COMPILATION;
        $this->outcome = WorkerEventOutcome::FAILED;
        $this->payload = [];
        $this->state = WorkerEventState::AWAITING;
        $this->label = 'non-empty label';
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

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param non-empty-string $label
     */
    public function withLabel(string $label): self
    {
        $new = clone $this;
        $new->label = $label;

        return $new;
    }
}
