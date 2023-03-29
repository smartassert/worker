<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Exception\JobNotFoundException;
use App\Message\TimeoutCheckMessage;
use App\MessageHandler\TimeoutCheckHandler;
use App\Repository\JobRepository;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EventRecorder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

class TimeoutCheckHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private TimeoutCheckHandler $handler;
    private EventRecorder $eventRecorder;
    private TransportInterface $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $timeoutCheckHandler = self::getContainer()->get(TimeoutCheckHandler::class);
        \assert($timeoutCheckHandler instanceof TimeoutCheckHandler);
        $this->handler = $timeoutCheckHandler;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof TransportInterface);
        $this->messengerTransport = $messengerTransport;
    }

    public function testInvokeNoJob(): void
    {
        self::assertCount(0, $this->messengerTransport->get());

        $message = new TimeoutCheckMessage();

        try {
            ($this->handler)($message);
            self::fail(JobNotFoundException::class . ' not thrown');
        } catch (JobNotFoundException) {
            self::assertCount(0, $this->messengerTransport->get());
        }

        self::assertSame(0, $this->eventRecorder->count());
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

        $message = new TimeoutCheckMessage();

        ($this->handler)($message);

        self::assertSame(0, $this->eventRecorder->count());

        $transportQueue = $this->messengerTransport->get();
        self::assertIsArray($transportQueue);
        self::assertCount(1, $transportQueue);

        $envelope = $transportQueue[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(new TimeoutCheckMessage(), $envelope->getMessage());

        $delayStamps = $envelope->all(DelayStamp::class);
        self::assertCount(1, $delayStamps);

        $delayStamp = $delayStamps[0];
        self::assertEquals(new DelayStamp(30000), $delayStamp);
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

        $message = new TimeoutCheckMessage();

        ($this->handler)($message);

        self::assertSame(1, $this->eventRecorder->count());

        $jobTimeoutEvent = $this->eventRecorder->get(0);
        self::assertInstanceOf(JobTimeoutEvent::class, $jobTimeoutEvent);

        $payload = $jobTimeoutEvent->getPayload();
        self::assertIsArray($payload);
        self::assertArrayHasKey('maximum_duration_in_seconds', $payload);
        self::assertSame($jobMaximumDuration, $payload['maximum_duration_in_seconds']);
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
