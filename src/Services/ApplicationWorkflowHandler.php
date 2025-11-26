<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEvent\JobCompilationEndedEvent;
use App\Event\EmittableEvent\JobCompilationStartedEvent;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobEndStateChangeEvent;
use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

class ApplicationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private readonly JobCompleteEventDispatcher $jobCompleteEventDispatcher,
        private readonly JobEndedEventFactory $jobEndedEventFactory,
        private readonly JobRepository $jobRepository,
    ) {}

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestEvent::class => [
                ['dispatchJobCompletedEventForTestPassedEvent', -100],
            ],
            JobEndStateChangeEvent::class => [
                ['dispatchJobEndedEventForJobEndStateChangeEvent', -100],
            ],
            JobStartedEvent::class => [
                ['dispatchJobCompilationStartedEventForJobStartedEvent', 0],
            ],
            JobCompiledEvent::class => [
                ['dispatchJobCompilationEndedEventForJobCompiledEvent', 0],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatchJobCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        $this->jobCompleteEventDispatcher->dispatch();
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchJobEndedEventForJobEndStateChangeEvent(JobEndStateChangeEvent $event): void
    {
        $jobEndedEvent = $this->jobEndedEventFactory->create();
        if (null === $jobEndedEvent) {
            return;
        }

        $this->eventDispatcher->dispatch($jobEndedEvent);
    }

    public function dispatchJobCompilationStartedEventForJobStartedEvent(JobStartedEvent $event): void
    {
        $this->eventDispatcher->dispatch(new JobCompilationStartedEvent($event->getLabel()));
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchJobCompilationEndedEventForJobCompiledEvent(JobCompiledEvent $event): void
    {
        $job = $this->jobRepository->get();

        $this->eventDispatcher->dispatch(new JobCompilationEndedEvent($job->getLabel()));
    }
}
