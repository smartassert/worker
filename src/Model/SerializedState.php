<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\StateInterface;

class SerializedState implements \JsonSerializable
{
    public function __construct(
        private readonly StateInterface $state
    ) {}

    /**
     * @return array{
     *   state: non-empty-string,
     *   meta_state: array{
     *     pending: bool,
     *     ended: bool,
     *     succeeded: bool,
     *   },
     * }
     */
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
}
