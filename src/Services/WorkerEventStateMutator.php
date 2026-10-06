<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventState;
use App\Event\EventDelivery\SendingEvent;
use App\Event\EventDelivery\SentEvent;
use App\Event\NotifiableEventDeliveryEvent;
use App\Message\DeliverEventMessage;
use App\Model\WorkerEventRemoteEventId;
use App\Repository\WorkerEventRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;

final readonly class WorkerEventStateMutator implements EventSubscriberInterface
{
    public function __construct(
        private WorkerEventRepository $repository,
        private EntityMutator $entityMutator,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SendingEvent::class => [
                ['setSendingForSendingEvent', 0],
            ],
            SentEvent::class => [
                ['setCompleteForSentEvent', 0],
            ],
            WorkerMessageReceivedEvent::class => [
                ['setSendingForWorkerMessageReceivedEvent', 0],
            ],
            WorkerMessageFailedEvent::class => [
                ['setFailedForWorkerMessageFailedEvent', 0],
            ],
        ];
    }

    public function setSendingForSendingEvent(SendingEvent $event): void
    {
        $this->setSending($event->workerEvent);
    }

    public function setCompleteForSentEvent(SentEvent $event): void
    {
        $this->setComplete($event->workerEvent);
    }

    public function setSendingForWorkerMessageReceivedEvent(WorkerMessageReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof SendWebhookMessage) {
            return;
        }

        $remoteEvent = $message->getEvent();
        if (NotifiableEventDeliveryEvent::REMOTE_EVENT_NAME !== $remoteEvent->getName()) {
            return;
        }

        $remoteEventId = $remoteEvent->getId();
        $workerEventId = WorkerEventRemoteEventId::fromString($remoteEventId)?->getWorkerEventId();
        if (null === $workerEventId) {
            return;
        }

        $workerEvent = $this->repository->find($workerEventId);
        if (null === $workerEvent) {
            return;
        }

        $this->setSending($workerEvent);
    }

    public function setFailedForWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): void
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

        $this->setFailed($workerEvent);
    }

    private function setSending(WorkerEvent $workerEvent): void
    {
        if (WorkerEventState::QUEUED === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEventState::SENDING);
        }
    }

    private function setFailed(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEventState::QUEUED, WorkerEventState::SENDING])) {
            $this->set($workerEvent, WorkerEventState::FAILED);
        }
    }

    private function setComplete(WorkerEvent $workerEvent): void
    {
        if (WorkerEventState::SENDING === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEventState::COMPLETE);
        }
    }

    private function set(WorkerEvent $workerEvent, WorkerEventState $state): void
    {
        $workerEvent->setState($state);

        $this->entityMutator->save($workerEvent);
    }
}
