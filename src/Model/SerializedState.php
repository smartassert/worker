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
        $isEndState = $this->state::isEndState($this->state);

        return [
            'state' => $this->state->getValue(),
            'meta_state' => [
                'pending' => $this->state::isPendingState($this->state),
                'ended' => $isEndState,
                'succeeded' => $this->state::isSuccessState($this->state),
            ],
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
