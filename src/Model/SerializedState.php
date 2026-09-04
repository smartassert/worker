<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\StateInterface;

class SerializedState implements SerializableComponentStateInterface
{
    public function __construct(
        private readonly StateInterface $state
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state->getValue(),
            'meta_state' => [
                'pending' => $this->state->isPending(),
                'ended' => $this->state->isEnd(),
                'succeeded' => $this->state->isSuccess(),
            ],
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
