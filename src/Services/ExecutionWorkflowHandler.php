<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Event\EmittableEvent\EventTypeInterface;
use App\Event\EmittableEvent\LifecycleEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Event\JobCompiledEvent;
use App\Exception\JobNotFoundException;
use App\MessageFactory\ExecuteTestMessageFactory;
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
        private readonly ExecuteTestMessageFactory $executeTestMessageFactory,
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
        if (EventTypeInterface::TEST_PASSED !== $event->getType()) {
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
            $message = $this->executeTestMessageFactory->create($testId);

            $this->messageBus->dispatch($message);
        }
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchExecutionStartedEventForJobCompiledEvent(JobCompiledEvent $event): void
    {
        $this->eventDispatcher->dispatch(new LifecycleEvent(
            $this->jobRepository->get()->getLabel(),
            EventTypeInterface::LIFECYCLE_EXECUTION_STARTED,
        ));
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchExecutionCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (EventTypeInterface::TEST_PASSED !== $event->getType()) {
            return;
        }

        $executionStateComplete = ExecutionState::COMPLETE === $this->executionProgress->get();

        $hasExecutionCompletedWorkerEvent = $this->workerEventRepository->hasForType(
            EventTypeInterface::LIFECYCLE_EXECUTION_COMPLETED,
        );

        if (true === $executionStateComplete && false === $hasExecutionCompletedWorkerEvent) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new LifecycleEvent(
                $job->getLabel(),
                EventTypeInterface::LIFECYCLE_EXECUTION_COMPLETED,
            ));
        }
    }
}
