<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\JobCompletedEvent;
use App\Message\JobCompletedCheckMessage;
use App\MessageHandler\JobCompletedCheckHandler;
use App\Services\ApplicationState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\Services\MockApplicationState;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Services\Asserter\MessengerAsserter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\ObjectReflector\ObjectReflector;

class JobCompletedCheckHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private JobCompletedCheckHandler $handler;
    private MessengerAsserter $messengerAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $jobCompletedCheckHandler = self::getContainer()->get(JobCompletedCheckHandler::class);
        \assert($jobCompletedCheckHandler instanceof JobCompletedCheckHandler);
        $this->handler = $jobCompletedCheckHandler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;
    }

    public function testInvokeApplicationStateNotComplete(): void
    {
        $applicationState = (new MockApplicationState())
            ->withIsCall(false, ApplicationState::STATE_COMPLETE)
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            JobCompletedCheckHandler::class,
            'applicationState',
            $applicationState
        );

        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            JobCompletedCheckHandler::class,
            'eventDispatcher',
            $eventDispatcher
        );

        ($this->handler)(new JobCompletedCheckMessage());
    }

    public function testInvokeApplicationStateIsComplete(): void
    {
        $applicationState = (new MockApplicationState())
            ->withIsCall(true, ApplicationState::STATE_COMPLETE)
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            JobCompletedCheckHandler::class,
            'applicationState',
            $applicationState
        );

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (Event $event) {
                    self::assertInstanceOf(JobCompletedEvent::class, $event);

                    return true;
                })
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            JobCompletedCheckHandler::class,
            'eventDispatcher',
            $eventDispatcher
        );

        ($this->handler)(new JobCompletedCheckMessage());
    }
}
