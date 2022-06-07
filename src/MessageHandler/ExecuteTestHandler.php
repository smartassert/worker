<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventType;
use App\Event\TestEvent;
use App\Event\TestPassedEvent;
use App\Exception\JobNotFoundException;
use App\Message\ExecuteTestMessage;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Services\ExecutionProgress;
use App\Services\TestDocumentFactory;
use App\Services\TestExecutor;
use App\Services\TestStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ExecuteTestHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private TestExecutor $testExecutor,
        private EventDispatcherInterface $eventDispatcher,
        private TestStateMutator $testStateMutator,
        private TestRepository $testRepository,
        private ExecutionProgress $executionProgress,
        private TestDocumentFactory $testDocumentFactory
    ) {
    }

    /**
     * @throws JobNotFoundException
     */
    public function __invoke(ExecuteTestMessage $message): void
    {
        $job = $this->jobRepository->get();

        if ($this->executionProgress->is(...ExecutionState::getFinishedStates())) {
            return;
        }

        $test = $this->testRepository->find($message->getTestId());
        if (null === $test) {
            return;
        }

        if (false === $test->hasState(TestState::AWAITING)) {
            return;
        }

        if (false === $job->hasStarted()) {
            $job->setStartDateTime();
            $this->jobRepository->add($job);
        }

        $testDocument = $this->testDocumentFactory->create($test);
        $testSource = $testDocument->getPath();
        if ('' === $testSource) {
            return;
        }

        $this->eventDispatcher->dispatch(new TestEvent(
            WorkerEventType::TEST_STARTED,
            $testSource,
            $test,
            $testDocument
        ));

        $this->testStateMutator->setRunning($test);
        $this->testExecutor->execute($test);
        $this->testStateMutator->setCompleteIfRunning($test);

        if ($test->hasState(TestState::COMPLETE)) {
            $this->eventDispatcher->dispatch(new TestPassedEvent(
                WorkerEventType::TEST_PASSED,
                $testSource,
                $test,
                $testDocument
            ));
        } else {
            $this->eventDispatcher->dispatch(new TestEvent(
                WorkerEventType::TEST_FAILED,
                $testSource,
                $test,
                $testDocument
            ));
        }
    }
}
