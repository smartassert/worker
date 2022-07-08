<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\EventInterface;
use App\Event\ExecutionEvent;
use App\Event\JobEvent;
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
use App\Services\CompilationWorkflowHandler;
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
                StepEvent::class => ['setFailedFromStepFailedEvent'],
            ],
            TestFactory::class => [
                SourceCompilationPassedEvent::class => ['createFromSourceCompileSuccessEvent'],
            ],
            ExecutionWorkflowHandler::class => [
                JobEvent::class => ['dispatchExecutionStartedEventForJobCompiledEvent'],
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
            CompilationWorkflowHandler::class => [
                JobStartedEvent::class => ['dispatchNextCompileSourceMessage'],
                SourceCompilationPassedEvent::class => [
                    'dispatchNextCompileSourceMessage',
                    'dispatchCompilationCompletedEvent'
                ],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(WorkerEvent::class);
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
     */
    public function testSubscribesToEvent(EventInterface $event): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();
        self::assertSame(0, $this->workerEventRepository->count([]));

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(1);

        $envelope = $this->messengerAsserter->getEnvelopeAtPosition(0);
        $message = $envelope->getMessage();
        self::assertInstanceOf(DeliverEventMessage::class, $message);
        self::assertSame(1, $this->workerEventRepository->count([]));
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

        $testSource = 'Test/test.yml';
        $testConfigurationBrowser = 'chrome';
        $testConfigurationUrl = 'http://example.com';

        $genericTest = new Test(
            $testConfigurationBrowser,
            $testConfigurationUrl,
            $testSource,
            '/app/target/GeneratedTest1234.php',
            ['step 1'],
            1
        );

        $testDocumentData = [
            'type' => 'test',
            'payload' => [
                'path' => $testSource,
                'config' => [
                    'browser' => $testConfigurationBrowser,
                    'url' => $testConfigurationUrl,
                ],
            ],
        ];

        $testDocument = new TestDocument($testSource, $testDocumentData);

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
                'event' => new JobStartedEvent(
                    self::JOB_LABEL,
                    [
                        'Test/test1.yaml',
                        'Test/test2.yaml',
                    ]
                ),
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($testSource),
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    $testSource,
                    $sourceCompilationPassedManifestCollection
                ),
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($testSource, $sourceCompileFailureEventOutput),
            ],
            'job/compiled' => [
                'event' => new JobEvent(self::JOB_LABEL, WorkerEventOutcome::COMPILED),
            ],
            'execution/started' => [
                'event' => new ExecutionEvent(self::JOB_LABEL, WorkerEventOutcome::STARTED),
            ],
            'test/started' => [
                'event' => new TestEvent($genericTest, $testDocument, $testSource, WorkerEventOutcome::STARTED),
            ],
            'step/passed' => [
                'event' => new StepEvent(
                    $genericTest->setState(TestState::RUNNING),
                    new Step('passing step', $passingStepDocumentData),
                    $testSource,
                    'passing step',
                    WorkerEventOutcome::PASSED
                ),
            ],
            'step/failed' => [
                'event' => new StepEvent(
                    $genericTest->setState(TestState::FAILED),
                    new Step('failing step', $failingStepDocumentData),
                    $testSource,
                    'failing step',
                    WorkerEventOutcome::FAILED
                ),
            ],
            'test/passed' => [
                'event' => new TestEvent(
                    $genericTest->setState(TestState::COMPLETE),
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::PASSED
                ),
            ],
            'test/failed' => [
                'event' => new TestEvent(
                    $genericTest->setState(TestState::FAILED),
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::FAILED
                ),
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(self::JOB_LABEL, 10),
            ],
            'job/completed' => [
                'event' => new JobEvent(self::JOB_LABEL, WorkerEventOutcome::COMPLETED),
            ],
            'job/failed' => [
                'event' => new JobEvent(self::JOB_LABEL, WorkerEventOutcome::FAILED),
            ],
            'execution/completed' => [
                'event' => new ExecutionEvent(self::JOB_LABEL, WorkerEventOutcome::COMPLETED),
            ],
        ];
    }
}
