<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobStartedEvent;
use App\Message\TimeoutCheckMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class TimeoutCheckMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private int $checkPeriodInMilliseconds,
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEvent::class => [
                ['dispatch', -100],
            ],
        ];
    }

    public function dispatch(): Envelope
    {
        return $this->messageBus->dispatch(
            new TimeoutCheckMessage(),
            [
                new DelayStamp($this->checkPeriodInMilliseconds),
            ]
        );
    }
}
