<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEvent\ExecutionEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Event\JobCompiledEvent;
use App\Exception\JobNotFoundException;
use App\Message\ExecuteTestMessage;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Repository\WorkerEventRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
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
    ) {}

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
            JobCompiledEvent::class => [
                ['dispatchNextExecuteTestMessageForJobCompiledEvent', -100],
                ['dispatchExecutionStartedEventForJobCompiledEvent', -50],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatchNextExecuteTestMessageForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        $test = $event->getTest();

        if (TestState::COMPLETE === $test->getState()) {
            $this->dispatchNextExecuteTestMessage();
        }
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatchNextExecuteTestMessageForJobCompiledEvent(JobCompiledEvent $event): void
    {
        $this->dispatchNextExecuteTestMessage();
    }

    /**
     * @throws ExceptionInterface
     */
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
    public function dispatchExecutionStartedEventForJobCompiledEvent(JobCompiledEvent $event): void
    {
        $this->eventDispatcher->dispatch(new ExecutionEvent(
            $this->jobRepository->get()->getLabel(),
            WorkerEventOutcome::STARTED
        ));
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchExecutionCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        $executionStateComplete = ExecutionState::COMPLETE === $this->executionProgress->get();

        $hasExecutionCompletedWorkerEvent = $this->workerEventRepository->hasForType(
            WorkerEventScope::EXECUTION,
            WorkerEventOutcome::COMPLETED
        );

        if (true === $executionStateComplete && false === $hasExecutionCompletedWorkerEvent) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new ExecutionEvent($job->getLabel(), WorkerEventOutcome::COMPLETED));
        }
    }
}
