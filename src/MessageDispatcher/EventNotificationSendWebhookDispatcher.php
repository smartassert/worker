<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\NotifiableEventDeliveryEvent;
use App\Model\WorkerEventRemoteEventId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Subscriber;

readonly class EventNotificationSendWebhookDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            NotifiableEventDeliveryEvent::class => [
                ['dispatch', 0],
            ],
        ];
    }

    /**
     * @throws MessengerExceptionInterface
     */
    public function dispatch(NotifiableEventDeliveryEvent $event): void
    {
        $subscriber = new Subscriber($event->job->getEventNotifyUrl(), $event->job->getEventNotifyToken());

        $remoteEvent = new RemoteEvent(
            name: $event->getRemoteEventName(),
            id: (string) WorkerEventRemoteEventId::fromWorkerEvent($event->getWorkerEvent()),
            payload: $event->getPayload(),
        );

        $this->messageBus->dispatch(
            new SendWebhookMessage($subscriber, $remoteEvent),
        );
    }
}
