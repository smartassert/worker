<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\EventInterface;
use App\Event\JobCompletedEvent;
use App\Message\JobCompletedCheckMessage;
use App\MessageHandler\JobCompletedCheckHandler;
use App\Services\ApplicationProgress;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\Services\MockApplicationProgress;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
        $applicationProgress = (new MockApplicationProgress())
            ->withIsCall(false, ApplicationProgress::STATE_COMPLETE)
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            JobCompletedCheckHandler::class,
            'applicationProgress',
            $applicationProgress
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
        $applicationProgress = (new MockApplicationProgress())
            ->withIsCall(true, ApplicationProgress::STATE_COMPLETE)
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            JobCompletedCheckHandler::class,
            'applicationProgress',
            $applicationProgress
        );

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (EventInterface $event) use (&$eventExpectationCount) {
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
