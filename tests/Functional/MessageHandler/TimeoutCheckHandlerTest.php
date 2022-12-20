<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\EmittableEventInterface;
use App\Event\JobTimeoutEmittableEvent;
use App\Exception\JobNotFoundException;
use App\Message\TimeoutCheckMessage;
use App\MessageHandler\TimeoutCheckHandler;
use App\Repository\JobRepository;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
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
        $this->messengerAsserter->assertQueueCount(0);

        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'eventDispatcher', $eventDispatcher);

        $message = new TimeoutCheckMessage();

        try {
            ($this->handler)($message);
            self::fail(JobNotFoundException::class . ' not thrown');
        } catch (JobNotFoundException) {
            $this->messengerAsserter->assertQueueCount(0);
        }
    }

    public function testInvokeJobMaximumDurationNotReached(): void
    {
        $job = new Job(
            md5((string) rand()),
            'https://example.com/events',
            600,
            ['test.yml']
        );

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);
        self::assertNull($job->endState);

        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;
        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'eventDispatcher', $eventDispatcher);

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

        $job = $this->createJobWithMutatedStartDateTime(
            new Job(
                md5((string) rand()),
                'https://example.com/events',
                $jobMaximumDuration,
                ['test.yml']
            )
        );

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);
        self::assertNull($job->endState);

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (EmittableEventInterface $actualEvent) use ($jobMaximumDuration, &$eventExpectationCount) {
                        self::assertInstanceOf(JobTimeoutEmittableEvent::class, $actualEvent);

                        if ($actualEvent instanceof JobTimeoutEmittableEvent) {
                            $payload = $actualEvent->getPayload();
                            self::assertIsArray($payload);
                            self::assertArrayHasKey('maximum_duration_in_seconds', $payload);
                            self::assertSame($jobMaximumDuration, $payload['maximum_duration_in_seconds']);
                        }

                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, TimeoutCheckHandler::class, 'eventDispatcher', $eventDispatcher);

        $message = new TimeoutCheckMessage();

        ($this->handler)($message);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
        $this->messengerAsserter->assertQueueCount(0);
    }

    private function createJobWithMutatedStartDateTime(Job $job): Job
    {
        $reflectionClass = new \ReflectionClass($job);
        $reflectionJob = $reflectionClass->newInstanceWithoutConstructor();
        \assert($reflectionJob instanceof Job);

        $startDateTimeProperty = $reflectionClass->getProperty('startDateTime');
        $startDateTimeProperty->setValue(
            $reflectionJob,
            new \DateTimeImmutable('-' . $job->maximumDurationInSeconds . ' second')
        );

        $maximumDurationInSecondsProperty = $reflectionClass->getProperty('maximumDurationInSeconds');
        $maximumDurationInSecondsProperty->setValue($reflectionJob, $job->maximumDurationInSeconds);

        $labelProperty = $reflectionClass->getProperty('label');
        $labelProperty->setValue($reflectionJob, $job->label);

        $eventDeliveryUrlProperty = $reflectionClass->getProperty('eventDeliveryUrl');
        $eventDeliveryUrlProperty->setValue($reflectionJob, $job->eventDeliveryUrl);

        $testPathsProperty = $reflectionClass->getProperty('testPaths');
        $testPathsProperty->setValue($reflectionJob, $job->testPaths);

        $endStateProperty = $reflectionClass->getProperty('endState');
        $endStateProperty->setValue($reflectionJob, null);

        return $reflectionJob;
    }
}
