<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Test as TestEntity;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\TestEvent;
use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Exception\JobNotFoundException;
use App\Message\ExecuteTestMessage;
use App\Model\Document\Test as TestDocument;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Services\ExecutionProgress;
use App\Services\TestExecutor;
use App\Services\TestStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
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

        $testDocument = $this->createTestDocumentFromTestEntity($test);
        $path = $test->getSource();

        $this->eventDispatcher->dispatch(new TestEvent($test, $testDocument, $path, WorkerEventOutcome::STARTED));

        $this->testStateMutator->setRunning($test);
        $this->testExecutor->execute($test);
        $this->testStateMutator->setCompleteIfRunning($test);

        $eventOutcome = $test->hasState(TestState::COMPLETE) ? WorkerEventOutcome::PASSED : WorkerEventOutcome::FAILED;
        $this->eventDispatcher->dispatch(new TestEvent($test, $testDocument, $path, $eventOutcome));
    }

    public function createTestDocumentFromTestEntity(TestEntity $testEntity): TestDocument
    {
        return new TestDocument($testEntity->getSource(), [
            'type' => 'test',
            'payload' => [
                'path' => $testEntity->getSource(),
                'config' => [
                    'browser' => $testEntity->getBrowser(),
                    'url' => $testEntity->getUrl(),
                ],
            ],
        ]);
    }
}
