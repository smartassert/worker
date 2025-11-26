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
use App\Tests\Services\EntityRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class JobTimeoutMessageOrderTest extends WebTestCase
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
            'results-token',
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
        self::assertSame(WorkerEventScope::JOB, $jobTimeoutEvent->scope);
        self::assertSame(WorkerEventOutcome::TIME_OUT, $jobTimeoutEvent->outcome);

        $jobEndedEvent = $workerEvents[1];
        self::assertSame(WorkerEventScope::JOB, $jobEndedEvent->scope);
        self::assertSame(WorkerEventOutcome::ENDED, $jobEndedEvent->outcome);
    }
}
