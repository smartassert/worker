<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Test;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Message\ExecuteTestMessage;
use App\Model\Document\Test as TestDocument;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Services\ExecutionState;
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
        private ExecutionState $executionState,
        private TestDocumentFactory $testDocumentFactory
    ) {
    }

    public function __invoke(ExecuteTestMessage $message): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        if ($this->executionState->is(...ExecutionState::FINISHED_STATES)) {
            return;
        }

        $test = $this->testRepository->find($message->getTestId());
        if (null === $test) {
            return;
        }

        if (false === $test->hasState(Test::STATE_AWAITING)) {
            return;
        }

        if (false === $job->hasStarted()) {
            $job->setStartDateTime();
            $this->jobRepository->add($job);
        }

        $document = $this->testDocumentFactory->create($test);
        $testDocument = new TestDocument($document);

        $this->eventDispatcher->dispatch(new TestStartedEvent($test, $document, $testDocument->getPath()));

        $this->testStateMutator->setRunning($test);
        $this->testExecutor->execute($test);
        $this->testStateMutator->setCompleteIfRunning($test);

        if ($test->hasState(Test::STATE_COMPLETE)) {
            $this->eventDispatcher->dispatch(new TestPassedEvent($test, $document, $testDocument->getPath()));
        } else {
            $this->eventDispatcher->dispatch(new TestFailedEvent($test, $document, $testDocument->getPath()));
        }
    }
}
