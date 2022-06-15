<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobStartedEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepEvent;
use App\Event\TestEvent;
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
use App\Tests\Mock\MockTestManifest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\BasilCompilerModels\TestManifestCollection;
use webignition\YamlDocument\Document;

class DeliverEventMessageDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const JOB_LABEL = 'label content';

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
                StepEvent::class => ['setFailedFromStepFailedEvent'],
            ],
            TestFactory::class => [
                SourceCompilationPassedEvent::class => ['createFromSourceCompileSuccessEvent'],
            ],
            ExecutionWorkflowHandler::class => [
                JobCompiledEvent::class => ['dispatchExecutionStartedEvent'],
            ],
            TimeoutCheckMessageDispatcher::class => [
                JobStartedEvent::class => ['dispatch'],
            ],
            ApplicationWorkflowHandler::class => [
                TestEvent::class => [
                    'dispatchJobFailedEventForTestFailedEvent',
                    'dispatchJobCompletedEventForTestPassedEvent',
                ],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Test::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        if ($environmentFactory instanceof EnvironmentFactory) {
            $environmentFactory->create(
                (new EnvironmentSetup())->withJobSetup(
                    (new JobSetup())->withLabel(self::JOB_LABEL)
                )
            );
        }
    }

    /**
     * @dataProvider subscribesToEventDataProvider
     *
     * @param array<mixed> $expectedWorkerEventPayload
     */
    public function testSubscribesToEvent(
        EventInterface $event,
        WorkerEventScope $expectedWorkerEventScope,
        WorkerEventOutcome $expectedWorkerEventOutcome,
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
        self::assertSame($expectedWorkerEventScope->value, $workerEvent->getScope()->value);
        self::assertSame($expectedWorkerEventOutcome->value, $workerEvent->getOutcome()->value);
        self::assertSame($expectedWorkerEventPayload, $workerEvent->getPayload());
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToEventDataProvider(): array
    {
        $sourceCompileFailureEventOutput = \Mockery::mock(ErrorOutputInterface::class);
        $sourceCompileFailureEventOutput
            ->shouldReceive('toArray')
            ->andReturn([
                'compile-failure-key' => 'value',
            ])
        ;

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

        $testConfigurationBrowser = 'chrome';
        $testConfigurationUrl = 'http://example.com';

        $genericTest = new Test($testConfigurationBrowser, $testConfigurationUrl, $testSource, '', ['step 1'], 1);

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

        $sourceCompilationPassedManifestCollection = new TestManifestCollection([
            (new MockTestManifest())
                ->withGetStepNamesCall([
                    'step one',
                    'step two',
                ])
                ->getMock(),
        ]);

        return [
            JobStartedEvent::class => [
                'event' => new JobStartedEvent([
                    'Test/test1.yaml',
                    'Test/test2.yaml',
                ]),
                'expectedWorkerEventScope' => WorkerEventScope::JOB,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::STARTED,
                'expectedWorkerEventPayload' => [
                    'tests' => [
                        'Test/test1.yaml',
                        'Test/test2.yaml',
                    ],
                    'related_references' => [
                        [
                            'label' => 'Test/test1.yaml',
                            'reference' => md5(self::JOB_LABEL . 'Test/test1.yaml'),
                        ],
                        [
                            'label' => 'Test/test2.yaml',
                            'reference' => md5(self::JOB_LABEL . 'Test/test2.yaml'),
                        ],
                    ],
                ],
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($relativeTestSource),
                'expectedWorkerEventScope' => WorkerEventScope::COMPILATION,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                ],
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    $relativeTestSource,
                    $sourceCompilationPassedManifestCollection
                ),
                'expectedWorkerEventScope' => WorkerEventScope::COMPILATION,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'related_references' => [
                        [
                            'label' => 'step one',
                            'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step one'),
                        ],
                        [
                            'label' => 'step two',
                            'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step two'),
                        ],
                    ],
                ],
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($relativeTestSource, $sourceCompileFailureEventOutput),
                'expectedWorkerEventScope' => WorkerEventScope::COMPILATION,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'output' => [
                        'compile-failure-key' => 'value',
                    ],
                ],
            ],
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedWorkerEventScope' => WorkerEventScope::JOB,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::COMPILED,
                'expectedWorkerEventPayload' => [],
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedWorkerEventScope' => WorkerEventScope::EXECUTION,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::STARTED,
                'expectedWorkerEventPayload' => [],
            ],
            WorkerEventType::TEST_STARTED->value => [
                'event' => new TestEvent(
                    WorkerEventOutcome::STARTED,
                    WorkerEventType::TEST_STARTED,
                    $relativeTestSource,
                    $genericTest,
                    $testDocument
                ),
                'expectedWorkerEventScope' => WorkerEventScope::TEST,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $testDocumentData,
                    'step_names' => [
                        'step 1',
                    ],
                    'related_references' => [
                        [
                            'label' => 'step 1',
                            'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step 1'),
                        ],
                    ],
                ],
            ],
            WorkerEventType::STEP_PASSED->value => [
                'event' => new StepEvent(
                    WorkerEventOutcome::PASSED,
                    WorkerEventType::STEP_PASSED,
                    new Step($passingStepDocument),
                    $relativeTestSource,
                    $genericTest->setState(TestState::RUNNING)
                ),
                'expectedWorkerEventScope' => WorkerEventScope::STEP,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $passingStepDocumentData,
                    'name' => 'passing step',
                ],
            ],
            WorkerEventType::STEP_FAILED->value => [
                'event' => new StepEvent(
                    WorkerEventOutcome::FAILED,
                    WorkerEventType::STEP_FAILED,
                    new Step($failingStepDocument),
                    $relativeTestSource,
                    $genericTest->setState(TestState::FAILED)
                ),
                'expectedWorkerEventScope' => WorkerEventScope::STEP,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $failingStepDocumentData,
                    'name' => 'failing step',
                ],
            ],
            WorkerEventType::TEST_PASSED->value => [
                'event' => new TestEvent(
                    WorkerEventOutcome::PASSED,
                    WorkerEventType::TEST_PASSED,
                    $relativeTestSource,
                    $genericTest->setState(TestState::COMPLETE),
                    $testDocument
                ),
                'expectedWorkerEventScope' => WorkerEventScope::TEST,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $testDocumentData,
                    'step_names' => [
                        'step 1',
                    ],
                    'related_references' => [
                        [
                            'label' => 'step 1',
                            'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step 1'),
                        ],
                    ],
                ],
            ],
            WorkerEventType::TEST_FAILED->value => [
                'event' => new TestEvent(
                    WorkerEventOutcome::FAILED,
                    WorkerEventType::TEST_FAILED,
                    $relativeTestSource,
                    $genericTest->setState(TestState::FAILED),
                    $testDocument
                ),
                'expectedWorkerEventScope' => WorkerEventScope::TEST,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $testDocumentData,
                    'step_names' => [
                        'step 1',
                    ],
                    'related_references' => [
                        [
                            'label' => 'step 1',
                            'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step 1'),
                        ],
                    ],
                ],
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expectedWorkerEventScope' => WorkerEventScope::JOB,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::TIME_OUT,
                'expectedWorkerEventPayload' => [
                    'maximum_duration_in_seconds' => 10,
                ],
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedWorkerEventScope' => WorkerEventScope::JOB,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::COMPLETED,
                'expectedWorkerEventPayload' => [],
            ],
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expectedWorkerEventScope' => WorkerEventScope::JOB,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::FAILED,
                'expectedWorkerEventPayload' => [],
            ],
            ExecutionCompletedEvent::class => [
                'event' => new ExecutionCompletedEvent(),
                'expectedWorkerEventScope' => WorkerEventScope::EXECUTION,
                'expectedWorkerEventOutcome' => WorkerEventOutcome::COMPLETED,
                'expectedWorkerEventPayload' => [],
            ],
        ];
    }
}
