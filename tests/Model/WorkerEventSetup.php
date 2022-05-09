<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\WorkerEvent;

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

    /**
     * @var WorkerEvent::STATE_*
     */
    private string $state;

    public function __construct()
    {
        $this->type = WorkerEvent::TYPE_COMPILATION_FAILED;
        $this->payload = [];
        $this->state = WorkerEvent::STATE_AWAITING;
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

    /**
     * @return WorkerEvent::STATE_*
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param WorkerEvent::STATE_* $state
     *
     * @return $this
     */
    public function withState(string $state): self
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
