<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Event\EventDelivery\SendingEvent;
use App\Event\EventDelivery\SentEvent;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class EventDeliveryProgressEventDispatcher implements MiddlewareInterface
{
    public function __construct(
        private WorkerEventRepository $workerEventRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        if (DeliverEventMessage::class !== $message::class) {
            return $stack->next()->handle($envelope, $stack);
        }

        $eventEntity = $this->workerEventRepository->find($message->workerEventId);
        if (null === $eventEntity) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->eventDispatcher->dispatch(new SendingEvent($eventEntity));

        $envelope = $stack->next()->handle($envelope, $stack);

        $this->eventDispatcher->dispatch(new SentEvent($eventEntity));

        return $envelope;
    }
}
