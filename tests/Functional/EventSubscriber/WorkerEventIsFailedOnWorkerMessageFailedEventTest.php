<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber;

use App\Entity\Job;
use App\Enum\WorkerEventState;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
use App\Services\EntityMutator;
use App\Services\WorkerEventFactory;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class WorkerEventIsFailedOnWorkerMessageFailedEventTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testWorkerEventIsFailed(): void
    {
        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);

        $environmentFactory->create(
            new EnvironmentSetup()
                ->withJobSetup(
                    new JobSetup(),
                ),
        );

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $job = $jobRepository->get();
        self::assertInstanceOf(Job::class, $job);

        $workerEventFactory = self::getContainer()->get(WorkerEventFactory::class);
        \assert($workerEventFactory instanceof WorkerEventFactory);

        $emittableEvent = new JobStartedEvent($job->getLabel(), []);

        $workerEvent = $workerEventFactory->create($job, $emittableEvent);
        $workerEvent->setState(WorkerEventState::SENDING);

        $entityMutator = self::getContainer()->get(EntityMutator::class);
        \assert($entityMutator instanceof EntityMutator);
        $entityMutator->save($workerEvent);

        $message = new DeliverEventMessage($workerEvent->getId());
        $envelope = new Envelope($message);

        $workerMessageFailedEvent = new WorkerMessageFailedEvent(
            $envelope,
            'receiver-name',
            new \Exception(),
        );

        self::assertSame(WorkerEventState::SENDING, $workerEvent->getState());

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($workerMessageFailedEvent);

        self::assertSame(WorkerEventState::FAILED, $workerEvent->getState());
    }
}
