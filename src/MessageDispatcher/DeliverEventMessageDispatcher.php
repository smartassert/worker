<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\EmittableEvent\CompilationFailedEvent;
use App\Event\EmittableEvent\CompilationPassedEvent;
use App\Event\EmittableEvent\CompilationStartedEvent;
use App\Event\EmittableEvent\CompilationTimedOutEvent;
use App\Event\EmittableEvent\EmittableEventInterface;
use App\Event\EmittableEvent\JobEndedEvent;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Event\EmittableEvent\LifecycleCompilationCompletedEvent;
use App\Event\EmittableEvent\LifecycleCompilationStartedEvent;
use App\Event\EmittableEvent\LifecycleExecutionEvent;
use App\Event\EmittableEvent\StepEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Exception\JobNotFoundException;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventFactory;
use App\Services\WorkerEventStateMutator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliverEventMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly WorkerEventStateMutator $workerEventStateMutator,
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly JobRepository $jobRepository,
        private readonly WorkerEventFactory $workerEventFactory,
    ) {}

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            CompilationStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            CompilationPassedEvent::class => [
                ['dispatchForEvent', 500],
            ],
            CompilationFailedEvent::class => [
                ['dispatchForEvent', 200],
            ],
            CompilationTimedOutEvent::class => [
                ['dispatchForEvent', 200],
            ],
            LifecycleExecutionEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobTimeoutEvent::class => [
                ['dispatchForEvent', 200],
            ],
            TestEvent::class => [
                ['dispatchForEvent', 100],
            ],
            StepEvent::class => [
                ['dispatchForEvent', 100],
            ],
            JobEndedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            LifecycleCompilationStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            LifecycleCompilationCompletedEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     * @throws ExceptionInterface
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
