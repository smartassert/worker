<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Entity\WorkerEventType;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\TestPassedEvent;
use App\Message\ExecuteTestMessage;
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
        private ExecutionState $executionState,
        private WorkerEventRepository $workerEventRepository,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestPassedEvent::class => [
                ['dispatchNextExecuteTestMessageFromTestPassedEvent', 0],
                ['dispatchExecutionCompletedEvent', 10],
            ],
            JobCompiledEvent::class => [
                ['dispatchNextExecuteTestMessage', 0],
                ['dispatchExecutionStartedEvent', 50],
            ],
        ];
    }

    public function dispatchNextExecuteTestMessageFromTestPassedEvent(TestPassedEvent $event): void
    {
        $test = $event->getTest();

        if ($test->hasState(Test::STATE_COMPLETE)) {
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

    public function dispatchExecutionStartedEvent(): void
    {
        $this->eventDispatcher->dispatch(new ExecutionStartedEvent());
    }

    public function dispatchExecutionCompletedEvent(): void
    {
        $executionStateComplete = $this->executionState->is(ExecutionState::STATE_COMPLETE);
        $hasExecutionCompletedWorkerEvent = $this->workerEventRepository->hasForType(
            WorkerEventType::EXECUTION_COMPLETED
        );

        if (true === $executionStateComplete && false === $hasExecutionCompletedWorkerEvent) {
            $this->eventDispatcher->dispatch(new ExecutionCompletedEvent());
        }
    }
}
