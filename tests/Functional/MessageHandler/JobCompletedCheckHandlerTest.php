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
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\ObjectReflector\ObjectReflector;

class JobCompletedCheckHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private JobCompletedCheckHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $jobCompletedCheckHandler = self::getContainer()->get(JobCompletedCheckHandler::class);
        \assert($jobCompletedCheckHandler instanceof JobCompletedCheckHandler);
        $this->handler = $jobCompletedCheckHandler;
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

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (Event $event) use (&$eventExpectationCount) {
                    self::assertInstanceOf(JobCompletedEvent::class, $event);
                    ++$eventExpectationCount;

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

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }
}
