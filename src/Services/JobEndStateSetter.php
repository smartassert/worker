<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobEndState;
use App\Event\EmittableEvent\CompilationFailedEvent;
use App\Event\EmittableEvent\EventTypeInterface;
use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Event\ExecutionCompletedEvent;
use App\Event\JobEndStateChangeEvent;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JobEndStateSetter implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly EntityMutator $entityMutator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

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
            CompilationFailedEvent::class => [
                ['setJobEndStateOnSourceCompilationFailedEvent', 100],
            ],
            ExecutionCompletedEvent::class => [
                ['setJobEndStateOnExecutionCompletedEvent', 100],
            ],
        ];
    }

    public function setJobEndStateOnExecutionCompletedEvent(ExecutionCompletedEvent $event): void
    {
        $this->setJobEndState(JobEndState::COMPLETE);
    }

    public function setJobEndStateOnJobTimeoutEvent(JobTimeoutEvent $event): void
    {
        $this->setJobEndState(JobEndState::TIMED_OUT);
    }

    public function setJobEndStateOnTestFailedEvent(TestEvent $event): void
    {
        $this->setJobEndStateOnTestEventWithType(
            $event,
            EventTypeInterface::TEST_FAILED,
            JobEndState::FAILED_TEST_FAILURE
        );
    }

    public function setJobEndStateOnSourceCompilationFailedEvent(CompilationFailedEvent $event): void
    {
        $this->setJobEndState(JobEndState::FAILED_COMPILATION);
    }

    public function setJobEndStateOnTestExceptionEvent(TestEvent $event): void
    {
        $this->setJobEndStateOnTestEventWithType(
            $event,
            EventTypeInterface::TEST_EXCEPTION,
            JobEndState::FAILED_TEST_EXCEPTION
        );
    }

    /**
     * @param EventTypeInterface::* $type
     */
    private function setJobEndStateOnTestEventWithType(TestEvent $event, string $type, JobEndState $state): void
    {
        if ($type !== $event->getType()) {
            return;
        }

        $this->setJobEndState($state);
    }

    private function setJobEndState(JobEndState $state): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        $job->setEndState($state);
        $this->entityMutator->save($job);

        $this->eventDispatcher->dispatch(new JobEndStateChangeEvent());
    }
}
