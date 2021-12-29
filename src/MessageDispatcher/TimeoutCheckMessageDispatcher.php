<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobReadyEvent;
use App\Message\TimeoutCheckMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TimeoutCheckMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobReadyEvent::class => [
                ['dispatch', 0],
            ],
        ];
    }

    public function dispatch(): Envelope
    {
        return $this->messageBus->dispatch(new TimeoutCheckMessage());
    }
}
