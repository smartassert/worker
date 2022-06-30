<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobEvent;
use App\Event\JobStartedEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepEvent;
use App\Event\TestEvent;
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
            JobStartedEvent::class => [
                ['dispatchForEvent', 0],
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
            JobEvent::class => [
                ['dispatchForEvent', 0],
            ],
            ExecutionStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            ExecutionCompletedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobTimeoutEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchForEvent(EventInterface $event): ?Envelope
    {
        $job = $this->jobRepository->get();

        $workerEvent = $this->workerEventFactory->create($job, $event);
        $this->workerEventRepository->add($workerEvent);

        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }
}
