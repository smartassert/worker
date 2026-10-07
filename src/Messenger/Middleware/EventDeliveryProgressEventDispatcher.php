<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Entity\WorkerEvent;
use App\Event\EventDelivery\SendingEvent;
use App\Event\EventDelivery\SentEvent;
use App\Event\NotifiableEventDeliveryEvent;
use App\Model\WorkerEventRemoteEventId;
use App\Repository\WorkerEventRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;

final readonly class EventDeliveryProgressEventDispatcher implements MiddlewareInterface
{
    public function __construct(
        private WorkerEventRepository $workerEventRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if (SendWebhookMessage::class !== $message::class) {
            return $stack->next()->handle($envelope, $stack);
        }

        $remoteEvent = $message->getEvent();
        if (NotifiableEventDeliveryEvent::REMOTE_EVENT_NAME !== $remoteEvent->getName()) {
            return $stack->next()->handle($envelope, $stack);
        }

        $workerEventRemoteEventId = WorkerEventRemoteEventId::fromString($remoteEvent->getId());
        if (!$workerEventRemoteEventId instanceof WorkerEventRemoteEventId) {
            return $stack->next()->handle($envelope, $stack);
        }

        $eventEntity = $this->workerEventRepository->find($workerEventRemoteEventId->getWorkerEventId());
        if (!$eventEntity instanceof WorkerEvent) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->eventDispatcher->dispatch(new SendingEvent($eventEntity));
        $envelope = $stack->next()->handle($envelope, $stack);
        $this->eventDispatcher->dispatch(new SentEvent($eventEntity));

        return $envelope;
    }
}
