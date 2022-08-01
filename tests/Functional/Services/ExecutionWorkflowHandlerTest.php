<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\TestEvent;
use App\Message\ExecuteTestMessage;
use App\Model\Document\Test as TestDocument;
use App\Services\ExecutionWorkflowHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use webignition\ObjectReflector\ObjectReflector;

class ExecutionWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    private ExecutionWorkflowHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $executionWorkflowHandler = self::getContainer()->get(ExecutionWorkflowHandler::class);
        \assert($executionWorkflowHandler instanceof ExecutionWorkflowHandler);
        $this->handler = $executionWorkflowHandler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

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

            $expectedNextTestId = ObjectReflector::getProperty($expectedNextTest, 'id');
            self::assertIsInt($expectedNextTestId);

            $this->messengerAsserter->assertMessageAtPositionEquals(0, new ExecuteTestMessage($expectedNextTestId));
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

        $expectedNextTestId = ObjectReflector::getProperty($expectedNextTest, 'id');
        self::assertIsInt($expectedNextTestId);

        $this->messengerAsserter->assertMessageAtPositionEquals(0, new ExecuteTestMessage($expectedNextTestId));
    }
}
