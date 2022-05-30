<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\Job;
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
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Exception\JobNotFoundException;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
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
        private readonly JobRepository $jobRepository,
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
            TestStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepPassedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestPassedEvent::class => [
                ['dispatchForEvent', 100],
            ],
            TestFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchForEvent(EventInterface $event): ?Envelope
    {
        $workerEvent = $this->createWorkerEvent($this->jobRepository->get(), $event);
        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }

    private function createWorkerEvent(Job $job, EventInterface $event): WorkerEvent
    {
        $payload = $event->getPayload();
        $relatedReferenceValues = $event->getRelatedReferenceValues();

        if (!array_key_exists('related_references', $payload) && [] !== $relatedReferenceValues) {
            $resourceReferenceCollection = $this->resourceReferenceFactory->createCollection(
                $job,
                $relatedReferenceValues
            );

            $payload['related_references'] = $resourceReferenceCollection->toArray();
        }

        $workerEvent = new WorkerEvent(
            $event->getType(),
            $this->referenceFactory->create($job->getLabel(), $event->getReferenceComponents()),
            $payload
        );

        return $this->workerEventRepository->add($workerEvent);
    }
}
