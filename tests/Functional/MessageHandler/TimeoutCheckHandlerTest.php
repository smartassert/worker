<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\EventInterface;
use App\Event\JobTimeoutEvent;
use App\Message\TimeoutCheckMessage;
use App\MessageHandler\TimeoutCheckHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\Repository\MockJobRepository;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use webignition\ObjectReflector\ObjectReflector;

class TimeoutCheckHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private TimeoutCheckHandler $handler;
    private MessengerAsserter $messengerAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $timeoutCheckHandler = self::getContainer()->get(TimeoutCheckHandler::class);
        \assert($timeoutCheckHandler instanceof TimeoutCheckHandler);
        $this->handler = $timeoutCheckHandler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testInvokeNoJob(): void
    {
        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'eventDispatcher', $eventDispatcher);

        $message = new TimeoutCheckMessage();

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueCount(0);
    }

    public function testInvokeJobMaximumDurationNotReached(): void
    {
        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        $job = new Job(
            md5((string) rand()),
            'https://example.com/events',
            600
        );

        $jobRepository = (new MockJobRepository())
            ->withGetCall($job)
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'eventDispatcher', $eventDispatcher);
        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'jobRepository', $jobRepository);

        $message = new TimeoutCheckMessage();

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, new TimeoutCheckMessage());
        $this->messengerAsserter->assertEnvelopeContainsStamp(
            $this->messengerAsserter->getEnvelopeAtPosition(0),
            new DelayStamp(30000),
            0
        );
    }

    public function testInvokeJobMaximumDurationReached(): void
    {
        $jobMaximumDuration = 123;

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (EventInterface $actualEvent) use ($jobMaximumDuration, &$eventExpectationCount) {
                        self::assertInstanceOf(JobTimeoutEvent::class, $actualEvent);

                        if ($actualEvent instanceof JobTimeoutEvent) {
                            self::assertSame($jobMaximumDuration, $actualEvent->getJobMaximumDuration());
                        }

                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        $job = new Job(
            md5((string) rand()),
            'https://example.com/events',
            $jobMaximumDuration
        );

        ObjectReflector::setProperty(
            $job,
            Job::class,
            'startDateTime',
            new \DateTimeImmutable('-' . $jobMaximumDuration . ' second')
        );

        $jobRepository = (new MockJobRepository())
            ->withGetCall($job)
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'eventDispatcher', $eventDispatcher);
        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'jobRepository', $jobRepository);

        $message = new TimeoutCheckMessage();

        ($this->handler)($message);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
        $this->messengerAsserter->assertQueueCount(0);
    }
}
