<?php

declare(strict_types=1);

namespace App\Message;

class DeliverEventMessage
{
    public function __construct(
        private int $workerEventId
    ) {
    }

    public function getWorkerEventId(): int
    {
        return $this->workerEventId;
    }
}
