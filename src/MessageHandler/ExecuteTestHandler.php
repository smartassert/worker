<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Exception\JobNotFoundException;
use App\Message\ExecuteTestMessage;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Services\DocumentFactory;
use App\Services\ExecutionProgress;
use App\Services\TestExecutor;
use App\Services\TestStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use webignition\BasilRunnerDocuments\Test as RunnerTest;
use webignition\BasilRunnerDocuments\TestConfiguration as RunnerTestConfiguration;
use webignition\TcpCliProxyClient\Exception\ClientCreationException;
use webignition\TcpCliProxyClient\Exception\SocketErrorException;

class ExecuteTestHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private TestExecutor $testExecutor,
        private EventDispatcherInterface $eventDispatcher,
        private TestStateMutator $testStateMutator,
        private TestRepository $testRepository,
        private ExecutionProgress $executionProgress,
        private DocumentFactory $documentFactory,
    ) {
    }

    /**
     * @throws JobNotFoundException
     * @throws ClientCreationException
     * @throws SocketErrorException
     * @throws InvalidDocumentException
     * @throws InvalidStepException
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

        $runnerTest = new RunnerTest(
            (string) $test->getSource(),
            new RunnerTestConfiguration($test->getBrowser(), $test->getUrl()),
        );

        $testDocument = $this->documentFactory->createTest($runnerTest->getData());

        $this->eventDispatcher->dispatch(new TestStartedEvent($testDocument));

        $this->testStateMutator->setRunning($test);
        $this->testExecutor->execute($test);
        $this->testStateMutator->setCompleteIfRunning($test);

        if ($test->hasState(TestState::COMPLETE)) {
            $this->eventDispatcher->dispatch(new TestPassedEvent($testDocument, $test));
        } else {
            $this->eventDispatcher->dispatch(new TestFailedEvent($testDocument));
        }
    }
}
