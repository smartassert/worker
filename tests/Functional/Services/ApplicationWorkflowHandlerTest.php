<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test as TestEntity;
use App\Enum\ApplicationState;
use App\Enum\ExecutionExceptionScope;
use App\Enum\WorkerEventOutcome;
use App\Event\EventInterface;
use App\Event\JobEvent;
use App\Event\TestEvent;
use App\Message\JobCompletedCheckMessage;
use App\MessageDispatcher\DeliverEventMessageDispatcher;
use App\Model\Document\Exception;
use App\Model\Document\Test as TestDocument;
use App\Services\ApplicationWorkflowHandler;
use App\Services\ExecutionWorkflowHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\Services\MockApplicationProgress;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\ObjectReflector\ObjectReflector;

class ApplicationWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private ApplicationWorkflowHandler $handler;
    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $applicationWorkflowHandler = self::getContainer()->get(ApplicationWorkflowHandler::class);
        \assert($applicationWorkflowHandler instanceof ApplicationWorkflowHandler);
        $this->handler = $applicationWorkflowHandler;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $eventListenerRemover->remove([
            DeliverEventMessageDispatcher::class => [
                TestEvent::class => ['dispatchForEvent'],
            ],
            ExecutionWorkflowHandler::class => [
                TestEvent::class => [
                    'dispatchExecutionCompletedEventForTestPassedEvent',
                    'dispatchNextExecuteTestMessageForTestPassedEvent',
                ],
            ],
        ]);
    }

    public function testSubscribesToTestPassedEventApplicationNotComplete(): void
    {
        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            ApplicationWorkflowHandler::class,
            'eventDispatcher',
            $eventDispatcher
        );

        $this->messengerAsserter->assertQueueIsEmpty();

        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);

        $this->eventDispatcher->dispatch(new TestEvent(
            $testEntity,
            new TestDocument('test.yml', []),
            'test.yml',
            WorkerEventOutcome::PASSED
        ));

        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new JobCompletedCheckMessage()
        );
    }

    public function testSubscribesToTestPassedEventApplicationComplete(): void
    {
        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (EventInterface $event) use (&$eventExpectationCount) {
                    self::assertInstanceOf(JobEvent::class, $event);
                    self::assertSame(WorkerEventOutcome::COMPLETED->value, $event->getOutcome()->value);
                    ++$eventExpectationCount;

                    return true;
                }),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            ApplicationWorkflowHandler::class,
            'eventDispatcher',
            $eventDispatcher
        );

        $applicationProgress = (new MockApplicationProgress())
            ->withIsCall(true, ApplicationState::COMPLETE)
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            ApplicationWorkflowHandler::class,
            'applicationProgress',
            $applicationProgress
        );

        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);
        $this->eventDispatcher->dispatch(new TestEvent(
            $testEntity,
            new TestDocument('test.yml', []),
            'test.yml',
            WorkerEventOutcome::PASSED
        ));

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }

    /**
     * @dataProvider subscribesToTestFailureEventDataProvider
     */
    public function testSubscribesToTestFailedEvent(TestEvent $event): void
    {
        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (EventInterface $event) use (&$eventExpectationCount) {
                    self::assertInstanceOf(JobEvent::class, $event);
                    self::assertSame(WorkerEventOutcome::FAILED->value, $event->getOutcome()->value);
                    ++$eventExpectationCount;

                    return true;
                }),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            ApplicationWorkflowHandler::class,
            'eventDispatcher',
            $eventDispatcher
        );

        $this->eventDispatcher->dispatch($event);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToTestFailureEventDataProvider(): array
    {
        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);

        return [
            'test/failed' => [
                'event' => new TestEvent(
                    $testEntity,
                    new TestDocument('test.yml', []),
                    'test.yml',
                    WorkerEventOutcome::FAILED
                ),
            ],
            'test/exception' => [
                'event' => new TestEvent(
                    $testEntity,
                    new Exception(ExecutionExceptionScope::TEST, []),
                    'test.yml',
                    WorkerEventOutcome::FAILED
                ),
            ],
        ];
    }
}
