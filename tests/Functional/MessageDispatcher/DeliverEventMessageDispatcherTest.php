<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventType;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
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
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\YamlDocument\Document;

class DeliverEventMessageDispatcherTest extends AbstractBaseFunctionalTest
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
                SourceCompilationPassedEvent::class => ['createFromSourceCompileSuccessEvent'],
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
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider subscribesToEventDataProvider
     *
     * @param array<mixed> $expectedWorkerEventPayload
     */
    public function testSubscribesToEvent(
        EventInterface $event,
        WorkerEventType $expectedWorkerEventType,
        array $expectedWorkerEventPayload
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(1);

        $envelope = $this->messengerAsserter->getEnvelopeAtPosition(0);
        $message = $envelope->getMessage();
        self::assertInstanceOf(DeliverEventMessage::class, $message);
        $workerEvent = $this->workerEventRepository->find($message->workerEventId);
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame($expectedWorkerEventType, $workerEvent->getType());
        self::assertSame($expectedWorkerEventPayload, $workerEvent->getPayload());
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

        $testConfigurationBrowser = 'chrome';
        $testConfigurationUrl = 'http://example.com';

        $testConfiguration = TestConfiguration::create($testConfigurationBrowser, $testConfigurationUrl);

        $passingStepDocumentData = [
            'type' => 'step',
            'payload' => [
                'name' => 'passing step',
            ],
        ];

        $passingStepDocument = new Document((string) json_encode($passingStepDocumentData));

        $failingStepDocumentData = [
            'type' => 'step',
            'payload' => [
                'name' => 'failing step',
            ],
        ];

        $failingStepDocument = new Document((string) json_encode($failingStepDocumentData));

        $relativeTestSource = 'Test/test.yml';
        $testSource = '/app/source/' . $relativeTestSource;

        $genericTest = Test::create($testConfiguration, $testSource, '', 1, 1);

        $testDocumentData = [
            'type' => 'test',
            'payload' => [
                'path' => $relativeTestSource,
                'config' => [
                    'browser' => $testConfigurationBrowser,
                    'url' => $testConfigurationUrl,
                ],
            ],
        ];

        $testDocument = new TestDocument(
            new Document((string) json_encode($testDocumentData))
        );

        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent([
                    'Test/test1.yaml',
                    'Test/test2.yaml',
                ]),
                'expectedWorkerEventType' => WorkerEventType::JOB_STARTED,
                'expectedWorkerEventPayload' => [
                    'tests' => [
                        'Test/test1.yaml',
                        'Test/test2.yaml',
                    ]
                ],
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($testSource),
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                ],
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent($testSource, (new MockSuiteManifest())->getMock()),
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                ],
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($testSource, $sourceCompileFailureEventOutput),
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                    'output' => [
                        'compile-failure-key' => 'value',
                    ],
                ],
            ],
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedWorkerEventType' => WorkerEventType::JOB_COMPILED,
                'expectedWorkerEventPayload' => [],
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedWorkerEventType' => WorkerEventType::EXECUTION_STARTED,
                'expectedWorkerEventPayload' => [],
            ],
            TestStartedEvent::class => [
                'event' => new TestStartedEvent($testDocument),
                'expectedWorkerEventType' => WorkerEventType::TEST_STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $testDocumentData,
                ],
            ],
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(new Step($passingStepDocument), $relativeTestSource),
                'expectedWorkerEventType' => WorkerEventType::STEP_PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $passingStepDocumentData,
                ],
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    new Step($failingStepDocument),
                    $relativeTestSource,
                    $genericTest->setState(TestState::FAILED)
                ),
                'expectedWorkerEventType' => WorkerEventType::STEP_FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $failingStepDocumentData,
                ],
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent(
                    $testDocument,
                    $genericTest->setState(TestState::COMPLETE)
                ),
                'expectedWorkerEventType' => WorkerEventType::TEST_PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $testDocumentData,
                ],
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent($testDocument),
                'expectedWorkerEventType' => WorkerEventType::TEST_FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $testDocumentData,
                ],
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expectedWorkerEventType' => WorkerEventType::JOB_TIME_OUT,
                'expectedWorkerEventPayload' => [
                    'maximum_duration_in_seconds' => 10,
                ],
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedWorkerEventType' => WorkerEventType::JOB_COMPLETED,
                'expectedWorkerEventPayload' => [],
            ],
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expectedWorkerEventType' => WorkerEventType::JOB_FAILED,
                'expectedWorkerEventPayload' => [],
            ],
            ExecutionCompletedEvent::class => [
                'event' => new ExecutionCompletedEvent(),
                'expectedWorkerEventType' => WorkerEventType::EXECUTION_COMPLETED,
                'expectedWorkerEventPayload' => [],
            ],
        ];
    }
}
