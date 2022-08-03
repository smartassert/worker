<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Message\TimeoutCheckMessage;
use App\Messenger\MessageFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class TimeoutCheckMessageDispatcher
{
    public function __construct(
        private readonly MessageFactory $messageFactory,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function dispatch(): void
    {
        $this->messageBus->dispatch(
            $this->messageFactory->createDelayedEnvelope(new TimeoutCheckMessage())
        );
    }
}
