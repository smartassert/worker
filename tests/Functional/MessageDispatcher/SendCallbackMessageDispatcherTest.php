<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Test as TestEntity;
use App\Entity\TestConfiguration;
use App\Entity\WorkerEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilation\FailedEvent;
use App\Event\SourceCompilation\PassedEvent;
use App\Event\SourceCompilation\StartedEvent;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Message\DeliverEventMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Model\Document\Step;
use App\Model\Document\Test as TestDocument;
use App\Repository\WorkerEventRepository;
use App\Services\ApplicationWorkflowHandler;
use App\Services\ExecutionWorkflowHandler;
use App\Services\TestFactory;
use App\Services\TestStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockSuiteManifest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\YamlDocument\Document;

class SendCallbackMessageDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $eventListenerRemover->remove([
            TestStateMutator::class => [
                StepFailedEvent::class => ['setFailedFromStepFailedEvent'],
            ],
            TestFactory::class => [
                PassedEvent::class => ['createFromSourceCompileSuccessEvent'],
            ],
            ExecutionWorkflowHandler::class => [
                JobCompiledEvent::class => ['dispatchExecutionStartedEvent'],
            ],
            TimeoutCheckMessageDispatcher::class => [
                JobReadyEvent::class => ['dispatch'],
            ],
            ApplicationWorkflowHandler::class => [
                TestFailedEvent::class => ['dispatchJobFailedEvent'],
                TestPassedEvent::class => ['dispatchJobCompletedEvent'],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(TestEntity::class);
        }
    }

    /**
     * @dataProvider subscribesToEventDataProvider
     *
     * @param array<mixed> $expectedWorkerEventPayload
     */
    public function testSubscribesToEvent(
        Event $event,
        string $expectedWorkerEventType,
        array $expectedWorkerEventPayload
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(1);

        $envelope = $this->messengerAsserter->getEnvelopeAtPosition(0);
        $message = $envelope->getMessage();
        self::assertInstanceOf(DeliverEventMessage::class, $message);

        if ($message instanceof DeliverEventMessage) {
            $workerEvent = $this->workerEventRepository->find($message->getWorkerEventId());
            self::assertInstanceOf(WorkerEvent::class, $workerEvent);

            if ($workerEvent instanceof WorkerEvent) {
                self::assertSame($expectedWorkerEventType, $workerEvent->getType());
                self::assertSame($expectedWorkerEventPayload, $workerEvent->getPayload());
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToEventDataProvider(): array
    {
        $sourceCompileFailureEventOutput = \Mockery::mock(ErrorOutputInterface::class);
        $sourceCompileFailureEventOutput
            ->shouldReceive('getData')
            ->andReturn([
                'compile-failure-key' => 'value',
            ])
        ;

        $testConfiguration = \Mockery::mock(TestConfiguration::class);

        $passingStepDocument = new Document('type: step' . "\n" . 'payload: { name: "passing step" }');
        $failingStepDocument = new Document('type: step' . "\n" . 'payload: { name: "failing step" }');

        $relativeTestSource = 'Test/test.yml';
        $testSource = '/app/source/' . $relativeTestSource;

        $genericTest = TestEntity::create($testConfiguration, $testSource, '', 1, 1);

        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedWorkerEventType' => WorkerEvent::TYPE_JOB_STARTED,
                'expectedWorkerEventPayload' => [],
            ],
            StartedEvent::class => [
                'event' => new StartedEvent($testSource),
                'expectedWorkerEventType' => WorkerEvent::TYPE_COMPILATION_STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                ],
            ],
            PassedEvent::class => [
                'event' => new PassedEvent($testSource, (new MockSuiteManifest())->getMock()),
                'expectedWorkerEventType' => WorkerEvent::TYPE_COMPILATION_PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                ],
            ],
            FailedEvent::class => [
                'event' => new FailedEvent($testSource, $sourceCompileFailureEventOutput),
                'expectedWorkerEventType' => WorkerEvent::TYPE_COMPILATION_FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                    'output' => [
                        'compile-failure-key' => 'value',
                    ],
                ],
            ],
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedWorkerEventType' => WorkerEvent::TYPE_JOB_COMPILED,
                'expectedWorkerEventPayload' => [],
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedWorkerEventType' => WorkerEvent::TYPE_EXECUTION_STARTED,
                'expectedWorkerEventPayload' => [],
            ],
            TestStartedEvent::class => [
                'event' => new TestStartedEvent(
                    $genericTest,
                    new TestDocument(new Document('document-key: value'))
                ),
                'expectedWorkerEventType' => WorkerEvent::TYPE_TEST_STARTED,
                'expectedWorkerEventPayload' => [
                    'document-key' => 'value',
                ],
            ],
            StepPassedEvent::class => [
                'event' => new StepPassedEvent($genericTest, new Step($passingStepDocument), $relativeTestSource),
                'expectedWorkerEventType' => WorkerEvent::TYPE_STEP_PASSED,
                'expectedWorkerEventPayload' => [
                    'type' => 'step',
                    'payload' => [
                        'name' => 'passing step',
                    ],
                ],
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    $genericTest->setState(TestEntity::STATE_FAILED),
                    new Step($failingStepDocument),
                    $relativeTestSource
                ),
                'expectedWorkerEventType' => WorkerEvent::TYPE_STEP_FAILED,
                'expectedWorkerEventPayload' => [
                    'type' => 'step',
                    'payload' => [
                        'name' => 'failing step',
                    ],
                ],
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent(
                    $genericTest->setState(TestEntity::STATE_COMPLETE),
                    new TestDocument(new Document('document-key: value'))
                ),
                'expectedWorkerEventType' => WorkerEvent::TYPE_TEST_PASSED,
                'expectedWorkerEventPayload' => [
                    'document-key' => 'value',
                ],
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent(
                    $genericTest->setState(TestEntity::STATE_FAILED),
                    new TestDocument(new Document('document-key: value'))
                ),
                'expectedWorkerEventType' => WorkerEvent::TYPE_TEST_FAILED,
                'expectedWorkerEventPayload' => [
                    'document-key' => 'value',
                ],
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expectedWorkerEventType' => WorkerEvent::TYPE_JOB_TIME_OUT,
                'expectedWorkerEventPayload' => [
                    'maximum_duration_in_seconds' => 10,
                ],
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedWorkerEventType' => WorkerEvent::TYPE_JOB_COMPLETED,
                'expectedWorkerEventPayload' => [],
            ],
        ];
    }
}
