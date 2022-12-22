<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventDispatcher;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\EventDispatcher\EventDispatcherInterface;

class JobTimeoutMessageOrderTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeForEntity(Job::class);
        $entityRemover->removeForEntity(WorkerEvent::class);
    }

    public function testJobTimeoutEventIsCreatedBeforeJobEndedEvent(): void
    {
        $workerEvents = $this->workerEventRepository->findAll();
        self::assertCount(0, $workerEvents);

        $job = new Job(
            md5((string) rand()),
            'https://example.com/events',
            600,
            ['test.yml']
        );

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $this->eventDispatcher->dispatch(new JobTimeoutEvent('job label', 5000));

        $workerEvents = $this->workerEventRepository->findAll();
        self::assertCount(2, $workerEvents);

        $jobTimeoutEvent = $workerEvents[0];
        self::assertInstanceOf(WorkerEvent::class, $jobTimeoutEvent);
        self::assertSame(WorkerEventScope::JOB, $jobTimeoutEvent->scope);
        self::assertSame(WorkerEventOutcome::TIME_OUT, $jobTimeoutEvent->outcome);

        $jobEndedEvent = $workerEvents[1];
        self::assertInstanceOf(WorkerEvent::class, $jobEndedEvent);
        self::assertSame(WorkerEventScope::JOB, $jobEndedEvent->scope);
        self::assertSame(WorkerEventOutcome::ENDED, $jobEndedEvent->outcome);
    }
}
