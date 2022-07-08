<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\ExecutionEvent;
use App\Event\JobEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\TestEvent;
use App\Message\DeliverEventMessage;
use App\Message\ExecuteTestMessage;
use App\MessageDispatcher\DeliverEventMessageDispatcher;
use App\Model\Document\Test as TestDocument;
use App\Repository\WorkerEventRepository;
use App\Services\ApplicationWorkflowHandler;
use App\Services\ExecutionWorkflowHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventListenerRemover;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ExecutionWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    private ExecutionWorkflowHandler $handler;
    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;
    private EnvironmentFactory $environmentFactory;
    private EventListenerRemover $eventListenerRemover;

    protected function setUp(): void
    {
        parent::setUp();

        $executionWorkflowHandler = self::getContainer()->get(ExecutionWorkflowHandler::class);
        \assert($executionWorkflowHandler instanceof ExecutionWorkflowHandler);
        $this->handler = $executionWorkflowHandler;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $this->eventListenerRemover = $eventListenerRemover;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    public function testDispatchNextExecuteTestMessageNoMessageDispatched(): void
    {
        $this->handler->dispatchNextExecuteTestMessage();
        $this->messengerAsserter->assertQueueIsEmpty();
    }

    /**
     * @dataProvider dispatchNextExecuteTestMessageMessageDispatchedDataProvider
     */
    public function testDispatchNextExecuteTestMessageMessageDispatched(
        EnvironmentSetup $setup,
        int $expectedNextTestIndex
    ): void {
        $this->doCompilationCompleteEventDrivenTest(
            $setup,
            function () {
                $this->handler->dispatchNextExecuteTestMessage();
            },
            $expectedNextTestIndex,
        );
    }

    /**
     * @return array<mixed>
     */
    public function dispatchNextExecuteTestMessageMessageDispatchedDataProvider(): array
    {
        return [
            'two tests, none run' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())->withSource('Test/test1.yml'),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ]),
                'expectedNextTestIndex' => 0,
            ],
            'three tests, first complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml'),
                        (new TestSetup())->withSource('Test/test3.yml'),
                    ]),
                'expectedNextTestIndex' => 1,
            ],
            'three tests, first, second complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test3.yml'),
                    ]),
                'expectedNextTestIndex' => 2,
            ],
        ];
    }

    public function testSubscribesToCompilationCompletedEvent(): void
    {
        $this->eventListenerRemover->remove([
            DeliverEventMessageDispatcher::class => [
                JobEvent::class => ['dispatchForEvent'],
                ExecutionEvent::class => ['dispatchForEvent'],
            ],
        ]);

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withTestSetups([
                (new TestSetup())
                    ->withSource('Test/test1.yml')
                    ->withState(TestState::COMPLETE),
                (new TestSetup())
                    ->withSource('Test/test2.yml'),
            ])
        ;

        $this->doCompilationCompleteEventDrivenTest(
            $environmentSetup,
            function () {
                $this->eventDispatcher->dispatch(new JobEvent('job label', WorkerEventOutcome::COMPILED));
            },
            1,
        );
    }

    /**
     * @dataProvider dispatchNextExecuteTestMessageFromTestPassedEventDataProvider
     */
    public function testDispatchNextExecuteTestMessageFromTestPassedEvent(
        EnvironmentSetup $setup,
        int $eventTestIndex,
        int $expectedQueuedMessageCount,
        ?int $expectedNextTestIndex
    ): void {
        $environment = $this->environmentFactory->create($setup);
        $tests = $environment->getTests();
        $this->messengerAsserter->assertQueueIsEmpty();

        $test = $tests[$eventTestIndex];
        $event = new TestEvent(
            $test,
            new TestDocument('test.yml', []),
            'test.yml',
            WorkerEventOutcome::PASSED
        );

        $this->handler->dispatchNextExecuteTestMessageForTestPassedEvent($event);

        $this->messengerAsserter->assertQueueCount($expectedQueuedMessageCount);

        if (is_int($expectedNextTestIndex)) {
            $expectedNextTest = $tests[$expectedNextTestIndex] ?? null;
            self::assertInstanceOf(Test::class, $expectedNextTest);

            $this->messengerAsserter->assertMessageAtPositionEquals(
                0,
                new ExecuteTestMessage((int) $expectedNextTest->getId())
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function dispatchNextExecuteTestMessageFromTestPassedEventDataProvider(): array
    {
        return [
            'single test, not complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::FAILED),
                    ]),
                'eventTestIndex' => 0,
                'expectedQueuedMessageCount' => 0,
                'expectedNextTestIndex' => null,
            ],
            'single test, is complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::CANCELLED),
                    ]),
                'eventTestIndex' => 0,
                'expectedQueuedMessageCount' => 0,
                'expectedNextTestIndex' => null,
            ],
            'multiple tests, not complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::FAILED),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::AWAITING),
                    ]),
                'eventTestIndex' => 0,
                'expectedQueuedMessageCount' => 0,
                'expectedNextTestIndex' => null,
            ],
            'multiple tests, complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::AWAITING),
                    ]),
                'eventTestIndex' => 0,
                'expectedQueuedMessageCount' => 1,
                'expectedNextTestIndex' => 1,
            ],
        ];
    }

    public function testSubscribesToTestPassedEventExecutionNotComplete(): void
    {
        $this->eventListenerRemover->remove([
            DeliverEventMessageDispatcher::class => [
                TestEvent::class => ['dispatchForEvent'],
            ],
            ApplicationWorkflowHandler::class => [
                TestEvent::class => ['dispatchJobCompletedEventForTestPassedEvent'],
            ],
        ]);

        $test0Source = 'Test/test1.yml';

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withTestSetups([
                (new TestSetup())
                    ->withSource($test0Source)
                    ->withState(TestState::COMPLETE),
                (new TestSetup())
                    ->withSource('Test/test2.yml')
                    ->withState(TestState::AWAITING),
            ])
        ;

        $environment = $this->environmentFactory->create($environmentSetup);
        $tests = $environment->getTests();

        $this->eventDispatcher->dispatch(
            new TestEvent(
                $tests[0],
                new TestDocument(
                    $test0Source,
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => $test0Source,
                        ],
                    ]
                ),
                $test0Source,
                WorkerEventOutcome::PASSED
            )
        );

        $this->messengerAsserter->assertQueueCount(1);

        $expectedNextTest = $tests[1] ?? null;
        self::assertInstanceOf(Test::class, $expectedNextTest);

        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new ExecuteTestMessage((int) $expectedNextTest->getId())
        );
    }

    public function testSubscribesToTestPassedEventExecutionComplete(): void
    {
        $this->eventListenerRemover->remove([
            DeliverEventMessageDispatcher::class => [
                SourceCompilationPassedEvent::class => ['dispatchForEvent'],
                JobEvent::class => ['dispatchForEvent'],
                ExecutionEvent::class => ['dispatchForEvent'],
            ],
            ApplicationWorkflowHandler::class => [
                TestEvent::class => ['dispatchJobCompletedEventForTestPassedEvent'],
            ],
        ]);

        $test0Source = 'Test/test1.yml';

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withTestSetups([
                (new TestSetup())
                    ->withSource($test0Source)
                    ->withState(TestState::COMPLETE),
                (new TestSetup())
                    ->withSource('Test/test2.yml')
                    ->withState(TestState::COMPLETE),
            ])
        ;

        $environment = $this->environmentFactory->create($environmentSetup);
        $tests = $environment->getTests();

        $this->eventDispatcher->dispatch(
            new TestEvent(
                $tests[0],
                new TestDocument(
                    $test0Source,
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => $test0Source,
                        ],
                    ]
                ),
                $test0Source,
                WorkerEventOutcome::PASSED
            )
        );

        $this->messengerAsserter->assertQueueCount(1);

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $workerEvents = $workerEventRepository->findAll();
        $expectedWorkerEvent = array_pop($workerEvents);

        self::assertInstanceOf(WorkerEvent::class, $expectedWorkerEvent);

        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new DeliverEventMessage((int) $expectedWorkerEvent->getId())
        );
    }

    private function doCompilationCompleteEventDrivenTest(
        EnvironmentSetup $setup,
        callable $execute,
        int $expectedNextTestIndex
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $environment = $this->environmentFactory->create($setup);
        $tests = $environment->getTests();

        $execute();

        $this->messengerAsserter->assertQueueCount(1);

        $expectedNextTest = $tests[$expectedNextTestIndex] ?? null;
        self::assertInstanceOf(Test::class, $expectedNextTest);

        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new ExecuteTestMessage((int) $expectedNextTest->getId())
        );
    }
}
