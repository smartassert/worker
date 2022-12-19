<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobEndState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\JobEndStateChangeEvent;
use App\Event\JobEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\TestEvent;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JobEndStateSetter implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestEvent::class => [
                ['setJobEndStateOnTestFailedEvent', 100],
                ['setJobEndStateOnTestExceptionEvent', 100],
            ],
            JobTimeoutEvent::class => [
                ['setJobEndStateOnJobTimeoutEvent', 100],
            ],
            SourceCompilationFailedEvent::class => [
                ['setJobEndStateOnSourceCompilationFailedEvent', 100],
            ],
            JobEvent::class => [
                ['setJobEndStateOnJobCompletedEvent', 100],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     */
    public function setJobEndStateOnJobCompletedEvent(JobEvent $event): void
    {
        if (WorkerEventOutcome::COMPLETED === $event->getOutcome()) {
            $this->setJobEndState(JobEndState::COMPLETE);
        }
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

        $this->eventDispatcher->dispatch(new JobEndStateChangeEvent());
    }
}
