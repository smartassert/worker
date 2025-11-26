<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\StateInterface;

class SerializedState implements \JsonSerializable
{
    public function __construct(
        private readonly StateInterface $state
    ) {
    }

    /**
     * @return array{state: non-empty-string, is_end_state: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state->getValue(),
            'is_end_state' => $this->state::isEndState($this->state),
        ];
    }
}
