<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\EmittableEvent\JobStartedEvent;
use App\Message\TimeoutCheckMessage;
use App\Messenger\MessageFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TimeoutCheckMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageFactory $messageFactory,
        private readonly MessageBusInterface $messageBus,
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
            $this->messageFactory->createDelayedEnvelope(new TimeoutCheckMessage())
        );
    }
}
