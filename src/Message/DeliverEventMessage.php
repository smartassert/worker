<?php

declare(strict_types=1);

namespace App\Message;

class DeliverEventMessage
{
    public function __construct(
        public readonly int $workerEventId
    ) {}
}
