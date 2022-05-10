<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;

class WorkerEventSetup
{
    /**
     * @var WorkerEvent::TYPE_*
     */
    private string $type;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private WorkerEventState $state;

    public function __construct()
    {
        $this->type = WorkerEvent::TYPE_COMPILATION_FAILED;
        $this->payload = [];
        $this->state = WorkerEventState::AWAITING;
    }

    /**
     * @return WorkerEvent::TYPE_*
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
     * @param WorkerEvent::TYPE_* $type
     *
     * @return $this
     */
    public function withType(string $type): self
    {
        $new = clone $this;
        $new->type = $type;

        return $new;
    }
}
