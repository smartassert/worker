<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Test as TestEntity;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\EmittableEvent\TestEvent;
use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Message\ExecuteTestMessage;
use App\Model\Document\Test as TestDocument;
use App\Repository\TestRepository;
use App\Services\ExecutionProgress;
use App\Services\TestExecutor;
use App\Services\TestStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use webignition\TcpCliProxyClient\Exception\ClientCreationException;
use webignition\TcpCliProxyClient\Exception\SocketErrorException;

#[AsMessageHandler]
class ExecuteTestHandler
{
    public function __construct(
        private TestExecutor $testExecutor,
        private EventDispatcherInterface $eventDispatcher,
        private TestStateMutator $testStateMutator,
        private TestRepository $testRepository,
        private ExecutionProgress $executionProgress,
    ) {}

    /**
     * @throws ClientCreationException
     * @throws SocketErrorException
     * @throws InvalidDocumentException
     * @throws InvalidStepException
     */
    public function __invoke(ExecuteTestMessage $message): void
    {
        if (ExecutionState::isEndState($this->executionProgress->get())) {
            return;
        }

        $test = $this->testRepository->find($message->getTestId());
        if (null === $test) {
            return;
        }

        if (TestState::AWAITING !== $test->getState()) {
            return;
        }

        $testDocument = $this->createTestDocumentFromTestEntity($test);
        $path = $test->getSource();

        $this->eventDispatcher->dispatch(
            new TestEvent($test, $testDocument, $path, WorkerEventOutcome::STARTED)
        );

        $this->testStateMutator->setRunning($test);
        $this->testExecutor->execute($test);
        $this->testStateMutator->setCompleteIfRunning($test);

        $eventOutcome = TestState::COMPLETE === $test->getState()
            ? WorkerEventOutcome::PASSED
            : WorkerEventOutcome::FAILED;

        $this->eventDispatcher->dispatch(new TestEvent($test, $testDocument, $path, $eventOutcome));
    }

    public function createTestDocumentFromTestEntity(TestEntity $testEntity): TestDocument
    {
        return new TestDocument(
            $testEntity->getSource(),
            [
                'type' => 'test',
                'payload' => [
                    'path' => $testEntity->getSource(),
                    'config' => [
                        'browser' => $testEntity->browser,
                        'url' => $testEntity->url,
                    ],
                ],
            ]
        );
    }
}
