<?php

declare(strict_types=1);

namespace App\Services;

use App\Message\SendCallbackMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class WorkerMessageFailedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CallbackAborter $callbackAborter,
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => [
                ['handleWorkerMessageFailedEvent', 0],
            ],
        ];
    }

    public function handleWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($message instanceof SendCallbackMessage && false === $event->willRetry()) {
            $this->callbackAborter->abort($message->getCallbackId());
        }
    }
}
