<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test as TestEntity;
use App\Enum\ApplicationState;
use App\Enum\WorkerEventOutcome;
use App\Event\EventInterface;
use App\Event\JobEvent;
use App\Event\JobFailedEvent;
use App\Event\TestEvent;
use App\Message\JobCompletedCheckMessage;
use App\MessageDispatcher\DeliverEventMessageDispatcher;
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

        $this->eventDispatcher->dispatch(new TestEvent(
            WorkerEventOutcome::PASSED,
            \Mockery::mock(TestEntity::class),
            new TestDocument('test.yml', [])
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

        $this->eventDispatcher->dispatch(new TestEvent(
            WorkerEventOutcome::PASSED,
            \Mockery::mock(TestEntity::class),
            new TestDocument('test.yml', [])
        ));

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }

    public function testSubscribesToTestFailedEvent(): void
    {
        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (EventInterface $event) use (&$eventExpectationCount) {
                    self::assertInstanceOf(JobFailedEvent::class, $event);
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

        $this->eventDispatcher->dispatch(new TestEvent(
            WorkerEventOutcome::FAILED,
            \Mockery::mock(TestEntity::class),
            new TestDocument('test.yml', [])
        ));

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }
}
