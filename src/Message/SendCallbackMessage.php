<?php

declare(strict_types=1);

namespace App\Message;

class SendCallbackMessage
{
    public function __construct(
        private int $callbackId
    ) {
    }

    public function getCallbackId(): int
    {
        return $this->callbackId;
    }
}
