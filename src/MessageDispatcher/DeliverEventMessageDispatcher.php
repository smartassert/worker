<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobStartedEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepEvent;
use App\Event\TestEvent;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use App\Services\ReferenceFactory;
use App\Services\ResourceReferenceFactory;
use App\Services\WorkerEventStateMutator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliverEventMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly WorkerEventStateMutator $workerEventStateMutator,
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly ReferenceFactory $referenceFactory,
        private readonly ResourceReferenceFactory $resourceReferenceFactory,
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEvent::class => [
                ['dispatchForEvent', 500],
            ],
            SourceCompilationStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            SourceCompilationPassedEvent::class => [
                ['dispatchForEvent', 500],
            ],
            SourceCompilationFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobCompiledEvent::class => [
                ['dispatchForEvent', 100],
            ],
            ExecutionStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            ExecutionCompletedEvent::class => [
                ['dispatchForEvent', 50],
            ],
            JobTimeoutEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobCompletedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    public function dispatchForEvent(EventInterface $event): ?Envelope
    {
        $workerEvent = $this->createWorkerEvent($event);
        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }

    private function createWorkerEvent(EventInterface $event): WorkerEvent
    {
        $payload = $event->getPayload();
        $relatedReferenceSources = $event->getRelatedReferenceSources();

        if (!array_key_exists('related_references', $payload) && [] !== $relatedReferenceSources) {
            $resourceReferenceCollection = $this->resourceReferenceFactory->createCollection($relatedReferenceSources);

            $payload['related_references'] = $resourceReferenceCollection->toArray();
        }

        $workerEvent = new WorkerEvent(
            $event->getScope(),
            $event->getOutcome(),
            $event->getType(),
            $this->referenceFactory->create($event->getReferenceComponents()),
            $payload
        );

        return $this->workerEventRepository->add($workerEvent);
    }
}
