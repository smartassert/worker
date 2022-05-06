<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\Callback\CallbackEntity;

class CallbackSetup
{
    /**
     * @var CallbackEntity::TYPE_*
     */
    private string $type;

    /**
     * @var array<mixed>
     */
    private array $payload;

    /**
     * @var CallbackEntity::STATE_*
     */
    private string $state;

    public function __construct()
    {
        $this->type = CallbackEntity::TYPE_COMPILATION_FAILED;
        $this->payload = [];
        $this->state = CallbackEntity::STATE_AWAITING;
    }

    /**
     * @return CallbackEntity::TYPE_*
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
     * @return CallbackEntity::STATE_*
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param CallbackEntity::STATE_* $state
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
     * @param CallbackEntity::TYPE_* $type
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
