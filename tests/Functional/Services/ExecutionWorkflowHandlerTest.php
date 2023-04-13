<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\EmittableEvent\TestEvent;
use App\Message\ExecuteTestMessage;
use App\Model\Document\Test as TestDocument;
use App\Services\ExecutionWorkflowHandler;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;
use webignition\ObjectReflector\ObjectReflector;

class ExecutionWorkflowHandlerTest extends WebTestCase
{
    private ExecutionWorkflowHandler $handler;
    private EnvironmentFactory $environmentFactory;
    private TransportInterface $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $executionWorkflowHandler = self::getContainer()->get(ExecutionWorkflowHandler::class);
        \assert($executionWorkflowHandler instanceof ExecutionWorkflowHandler);
        $this->handler = $executionWorkflowHandler;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Test::class);
        }

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof TransportInterface);
        $this->messengerTransport = $messengerTransport;
    }

    public function testDispatchNextExecuteTestMessageNoMessageDispatched(): void
    {
        $this->handler->dispatchNextExecuteTestMessage();
        self::assertCount(0, $this->messengerTransport->get());
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
        self::assertCount(0, $this->messengerTransport->get());

        $test = $tests[$eventTestIndex];
        $event = new TestEvent(
            $test,
            new TestDocument('test.yml', []),
            'test.yml',
            WorkerEventOutcome::PASSED
        );

        $this->handler->dispatchNextExecuteTestMessageForTestPassedEvent($event);

        self::assertCount($expectedQueuedMessageCount, $this->messengerTransport->get());

        if (is_int($expectedNextTestIndex)) {
            $expectedNextTest = $tests[$expectedNextTestIndex] ?? null;
            self::assertInstanceOf(Test::class, $expectedNextTest);

            $expectedNextTestId = ObjectReflector::getProperty($expectedNextTest, 'id');
            self::assertIsInt($expectedNextTestId);

            $transportQueue = $this->messengerTransport->get();
            self::assertIsArray($transportQueue);
            self::assertCount(1, $transportQueue);

            $envelope = $transportQueue[0];
            self::assertInstanceOf(Envelope::class, $envelope);
            self::assertEquals(new ExecuteTestMessage($expectedNextTestId), $envelope->getMessage());
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
        self::assertCount(0, $this->messengerTransport->get());

        $environment = $this->environmentFactory->create($setup);
        $tests = $environment->getTests();

        $execute();

        $expectedNextTest = $tests[$expectedNextTestIndex] ?? null;
        self::assertInstanceOf(Test::class, $expectedNextTest);

        $expectedNextTestId = ObjectReflector::getProperty($expectedNextTest, 'id');
        self::assertIsInt($expectedNextTestId);

        $transportQueue = $this->messengerTransport->get();
        self::assertIsArray($transportQueue);
        self::assertCount(1, $transportQueue);

        $envelope = $transportQueue[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(new ExecuteTestMessage($expectedNextTestId), $envelope->getMessage());
    }
}
