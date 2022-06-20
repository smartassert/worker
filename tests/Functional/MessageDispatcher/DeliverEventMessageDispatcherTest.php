<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
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
use App\Tests\Mock\MockTestManifest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;
use webignition\BasilCompilerModels\Model\TestManifestCollection;

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
                StepFailedEvent::class => ['setFailedFromStepFailedEvent'],
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
                TestFailedEvent::class => ['dispatchJobFailedEvent'],
                TestPassedEvent::class => ['dispatchJobCompletedEvent'],
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

        $failingStepDocumentData = [
            'type' => 'step',
            'payload' => [
                'name' => 'failing step',
            ],
        ];

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

        $testDocument = new TestDocument($testDocumentData);

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
                'expectedWorkerEventType' => WorkerEventType::JOB_STARTED,
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
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                ],
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    $relativeTestSource,
                    $sourceCompilationPassedManifestCollection
                ),
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_PASSED,
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
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_FAILED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
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
                'event' => new StepPassedEvent(new Step('passing step', $passingStepDocumentData), $relativeTestSource),
                'expectedWorkerEventType' => WorkerEventType::STEP_PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $relativeTestSource,
                    'document' => $passingStepDocumentData,
                ],
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    new Step('failing step', $failingStepDocumentData),
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
