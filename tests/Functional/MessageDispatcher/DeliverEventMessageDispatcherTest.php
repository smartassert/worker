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

        $testDocument = new TestDocument($relativeTestSource, $testDocumentData);

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
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($relativeTestSource),
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    $relativeTestSource,
                    $sourceCompilationPassedManifestCollection
                ),
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($relativeTestSource, $sourceCompileFailureEventOutput),
            ],
            'job/compiled' => [
                'event' => new JobEvent(WorkerEventOutcome::COMPILED),
            ],
            'execution/started' => [
                'event' => new ExecutionEvent(WorkerEventOutcome::STARTED),
            ],
            'test/started' => [
                'event' => new TestEvent(WorkerEventOutcome::STARTED, $genericTest, $testDocument),
            ],
            'step/passed' => [
                'event' => new StepEvent(
                    WorkerEventOutcome::PASSED,
                    new Step('passing step', $passingStepDocumentData),
                    $relativeTestSource,
                    $genericTest->setState(TestState::RUNNING)
                ),
            ],
            'step/failed' => [
                'event' => new StepEvent(
                    WorkerEventOutcome::FAILED,
                    new Step('failing step', $failingStepDocumentData),
                    $relativeTestSource,
                    $genericTest->setState(TestState::FAILED)
                ),
            ],
            'test/passed' => [
                'event' => new TestEvent(
                    WorkerEventOutcome::PASSED,
                    $genericTest->setState(TestState::COMPLETE),
                    $testDocument
                ),
            ],
            'test/failed' => [
                'event' => new TestEvent(
                    WorkerEventOutcome::FAILED,
                    $genericTest->setState(TestState::FAILED),
                    $testDocument
                ),
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
            ],
            'job/completed' => [
                'event' => new JobEvent(WorkerEventOutcome::COMPLETED),
            ],
            'job/failed' => [
                'event' => new JobEvent(WorkerEventOutcome::FAILED),
            ],
            'execution/completed' => [
                'event' => new ExecutionEvent(WorkerEventOutcome::COMPLETED),
            ],
        ];
    }
}
