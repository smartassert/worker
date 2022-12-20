<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\EmittableEventInterface;
use App\Event\ExecutionEmittableEvent;
use App\Event\JobEndedEmittableEvent;
use App\Event\JobStartedEmittableEvent;
use App\Event\JobTimeoutEmittableEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepEmittableEvent;
use App\Event\TestEmittableEvent;
use App\Exception\JobNotFoundException;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventFactory;
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
        private readonly JobRepository $jobRepository,
        private readonly WorkerEventFactory $workerEventFactory,
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEmittableEvent::class => [
                ['dispatchForEvent', 0],
            ],
            SourceCompilationStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            SourceCompilationPassedEvent::class => [
                ['dispatchForEvent', 500],
            ],
            SourceCompilationFailedEvent::class => [
                ['dispatchForEvent', 200],
            ],
            ExecutionEmittableEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobTimeoutEmittableEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestEmittableEvent::class => [
                ['dispatchForEvent', 100],
            ],
            StepEmittableEvent::class => [
                ['dispatchForEvent', 100],
            ],
            JobEndedEmittableEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchForEvent(EmittableEventInterface $event): ?Envelope
    {
        $job = $this->jobRepository->get();

        $workerEvent = $this->workerEventFactory->create($job, $event);
        $this->workerEventRepository->add($workerEvent);

        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }
}
