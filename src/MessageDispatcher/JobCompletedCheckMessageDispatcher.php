<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class JobCompletedCheckMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly int $checkPeriodInMilliseconds,
        private readonly object $message,
    ) {
    }

    public function dispatch(): void
    {
        $this->messageBus->dispatch(
            clone $this->message,
            [
                new DelayStamp($this->checkPeriodInMilliseconds),
            ]
        );
    }
}
