<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

readonly class WorkerMessageFailedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WorkerEventRepository $repository,
        private WorkerEventStateMutator $workerEventStateMutator,
    ) {}

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
        if ($event->willRetry()) {
            return;
        }

        if (!$message instanceof DeliverEventMessage) {
            return;
        }

        $workerEvent = $this->repository->find($message->workerEventId);
        if (!$workerEvent instanceof WorkerEvent) {
            return;
        }

        $this->workerEventStateMutator->setFailed($workerEvent);
    }
}
