<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Message\JobCompletedCheckMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class JobCompletedCheckMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly int $checkPeriodInMilliseconds,
    ) {
    }

    public function dispatch(): void
    {
        $this->messageBus->dispatch(
            new JobCompletedCheckMessage(),
            [
                new DelayStamp($this->checkPeriodInMilliseconds),
            ]
        );
    }
}
