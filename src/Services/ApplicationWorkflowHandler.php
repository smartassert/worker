<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobEndState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\JobEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\TestEvent;
use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplicationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private readonly JobRepository $jobRepository,
        private readonly JobCompleteEventDispatcher $jobCompleteEventDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestEvent::class => [
                ['dispatchJobCompletedEventForTestPassedEvent', -100],
                ['dispatchJobFailedEventForTestFailureEvent', -100],
                ['setJobEndStateOnTestFailedEvent', 100],
                ['setJobEndStateOnTestExceptionEvent', 100],
            ],
            JobTimeoutEvent::class => [
                ['setJobEndStateOnJobTimeoutEvent', 100],
            ],
            SourceCompilationFailedEvent::class => [
                ['setJobEndStateOnSourceCompilationFailedEvent', 100],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
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
    public function dispatchJobFailedEventForTestFailureEvent(TestEvent $event): void
    {
        if (
            WorkerEventScope::TEST !== $event->getScope()
            || !in_array($event->getOutcome(), [WorkerEventOutcome::FAILED, WorkerEventOutcome::EXCEPTION])
        ) {
            return;
        }

        $job = $this->jobRepository->get();
        $this->eventDispatcher->dispatch(new JobEvent($job->label, WorkerEventOutcome::FAILED));
    }

    /**
     * @throws JobNotFoundException
     */
    public function setJobEndStateOnJobTimeoutEvent(JobTimeoutEvent $event): void
    {
        $this->setJobEndState(JobEndState::TIMED_OUT);
    }

    /**
     * @throws JobNotFoundException
     */
    public function setJobEndStateOnTestFailedEvent(TestEvent $event): void
    {
        $this->setJobEndStateOnTestEventWithOutcome(
            $event,
            WorkerEventOutcome::FAILED,
            JobEndState::FAILED_TEST_FAILURE
        );
    }

    /**
     * @throws JobNotFoundException
     */
    public function setJobEndStateOnSourceCompilationFailedEvent(SourceCompilationFailedEvent $event): void
    {
        $this->setJobEndState(JobEndState::FAILED_COMPILATION);
    }

    /**
     * @throws JobNotFoundException
     */
    public function setJobEndStateOnTestExceptionEvent(TestEvent $event): void
    {
        $this->setJobEndStateOnTestEventWithOutcome(
            $event,
            WorkerEventOutcome::EXCEPTION,
            JobEndState::FAILED_TEST_EXCEPTION
        );
    }

    /**
     * @throws JobNotFoundException
     */
    private function setJobEndStateOnTestEventWithOutcome(
        TestEvent $event,
        WorkerEventOutcome $outcome,
        JobEndState $state
    ): void {
        if (
            !(WorkerEventScope::TEST === $event->getScope() && $outcome === $event->getOutcome())
        ) {
            return;
        }

        $this->setJobEndState($state);
    }

    /**
     * @throws JobNotFoundException
     */
    private function setJobEndState(JobEndState $state): void
    {
        $job = $this->jobRepository->get();
        $job->setEndState($state);
        $this->jobRepository->add($job);
    }
}
