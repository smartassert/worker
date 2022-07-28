<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\ExecutionEvent;
use App\Event\JobEvent;
use App\Event\TestEvent;
use App\Exception\JobNotFoundException;
use App\Message\ExecuteTestMessage;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Repository\WorkerEventRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ExecutionWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private TestRepository $testRepository,
        private ExecutionProgress $executionProgress,
        private WorkerEventRepository $workerEventRepository,
        private EventDispatcherInterface $eventDispatcher,
        private readonly JobRepository $jobRepository,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestEvent::class => [
                ['dispatchNextExecuteTestMessageForTestPassedEvent', -100],
                ['dispatchExecutionCompletedEventForTestPassedEvent', -90],
            ],
            JobEvent::class => [
                ['dispatchNextExecuteTestMessageForJobCompiledEvent', -100],
                ['dispatchExecutionStartedEventForJobCompiledEvent', -50],
            ],
        ];
    }

    public function dispatchNextExecuteTestMessageForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        $test = $event->getTest();

        if ($test->hasState(TestState::COMPLETE)) {
            $this->dispatchNextExecuteTestMessage();
        }
    }

    public function dispatchNextExecuteTestMessageForJobCompiledEvent(JobEvent $event): void
    {
        if (WorkerEventOutcome::COMPILED === $event->getOutcome()) {
            $this->dispatchNextExecuteTestMessage();
        }
    }

    public function dispatchNextExecuteTestMessage(): void
    {
        $testId = $this->testRepository->findNextAwaitingId();

        if (is_int($testId)) {
            $this->messageBus->dispatch(new ExecuteTestMessage($testId));
        }
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchExecutionStartedEventForJobCompiledEvent(JobEvent $event): void
    {
        if (WorkerEventOutcome::COMPILED === $event->getOutcome()) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new ExecutionEvent($job->label, WorkerEventOutcome::STARTED));
        }
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchExecutionCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        $executionStateComplete = $this->executionProgress->is(ExecutionState::COMPLETE);
        $hasExecutionCompletedWorkerEvent = $this->workerEventRepository->hasForType(
            WorkerEventScope::EXECUTION,
            WorkerEventOutcome::COMPLETED
        );

        if (true === $executionStateComplete && false === $hasExecutionCompletedWorkerEvent) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new ExecutionEvent($job->label, WorkerEventOutcome::COMPLETED));
        }
    }
}
