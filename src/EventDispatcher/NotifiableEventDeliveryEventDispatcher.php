<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\EmittableEvent\CompilationFailedEvent;
use App\Event\EmittableEvent\CompilationPassedEvent;
use App\Event\EmittableEvent\CompilationStartedEvent;
use App\Event\EmittableEvent\CompilationTimedOutEvent;
use App\Event\EmittableEvent\EmittableEventInterface;
use App\Event\EmittableEvent\JobEndedEvent;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Event\EmittableEvent\LifecycleEvent;
use App\Event\EmittableEvent\StepEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Event\NotifiableEventDeliveryEvent;
use App\Repository\JobRepository;
use App\Services\EntityMutator;
use App\Services\WorkerEventFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class NotifiableEventDeliveryEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRepository $jobRepository,
        private WorkerEventFactory $workerEventFactory,
        private readonly EntityMutator $entityMutator,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEvent::class => [
                ['dispatch', 0],
            ],
            CompilationStartedEvent::class => [
                ['dispatch', 0],
            ],
            CompilationPassedEvent::class => [
                ['dispatch', 500],
            ],
            CompilationFailedEvent::class => [
                ['dispatch', 200],
            ],
            CompilationTimedOutEvent::class => [
                ['dispatch', 200],
            ],
            LifecycleEvent::class => [
                ['dispatch', 0],
            ],
            JobTimeoutEvent::class => [
                ['dispatch', 200],
            ],
            TestEvent::class => [
                ['dispatch', 100],
            ],
            StepEvent::class => [
                ['dispatch', 100],
            ],
            JobEndedEvent::class => [
                ['dispatch', 0],
            ],
        ];
    }

    public function dispatch(EmittableEventInterface $event): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        $workerEvent = $this->workerEventFactory->create($job, $event);
        $this->entityMutator->save($workerEvent);

        $notifiableEvent = new NotifiableEventDeliveryEvent($job, $workerEvent, $event);

        $this->eventDispatcher->dispatch($notifiableEvent);
    }
}
