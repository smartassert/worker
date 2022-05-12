<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\JobReadyEvent;
use App\Message\JobReadyMessage;
use App\MessageHandler\JobReadyHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class JobReadyHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private JobReadyHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $jobReadyHandler = self::getContainer()->get(JobReadyHandler::class);
        \assert($jobReadyHandler instanceof JobReadyHandler);
        $this->handler = $jobReadyHandler;
    }

    public function testInvoke(): void
    {
        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (JobReadyEvent $actualEvent) use (&$eventExpectationCount) {
                        self::assertInstanceOf(JobReadyEvent::class, $actualEvent);
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, JobReadyHandler::class, 'eventDispatcher', $eventDispatcher);

        $message = new JobReadyMessage();

        ($this->handler)($message);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }
}
