<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\EmittableEvent\JobStartedEvent;
use App\Message\TimeoutCheckMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class TimeoutCheckMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly int $dispatchDelay,
    ) {
    }

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEvent::class => [
                ['dispatch', -100],
            ],
        ];
    }

    public function dispatch(): void
    {
        $this->messageBus->dispatch(
            new Envelope(new TimeoutCheckMessage(), [new DelayStamp($this->dispatchDelay)])
        );
    }
}
