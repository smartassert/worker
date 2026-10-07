<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventState;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EventDelivery\SendingEvent;
use App\Event\EventDelivery\SentEvent;
use App\Event\NotifiableEventDeliveryEvent;
use App\Model\WorkerEventRemoteEventId;
use App\Repository\JobRepository;
use App\Services\EntityMutator;
use App\Services\WorkerEventFactory;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Subscriber;
use Symfony\Contracts\EventDispatcher\Event;

class WorkerEventStateTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    private WorkerEvent $workerEvent;
    private EntityMutator $entityMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

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
        \assert($job instanceof Job);
        $job1 = $job;

        $emittableEvent = new JobStartedEvent($job1->getLabel(), []);

        $workerEventFactory = self::getContainer()->get(WorkerEventFactory::class);
        \assert($workerEventFactory instanceof WorkerEventFactory);
        $this->workerEvent = $workerEventFactory->create($job1, $emittableEvent);

        $entityMutator = self::getContainer()->get(EntityMutator::class);
        \assert($entityMutator instanceof EntityMutator);
        $this->entityMutator = $entityMutator;
    }

    /**
     * @param callable(WorkerEvent): Event $eventCreator
     */
    #[DataProvider('workerEventStateIsSetDataProvider')]
    public function testWorkerEventStateIsSet(
        WorkerEventState $startingState,
        callable $eventCreator,
        WorkerEventState $expectedState,
    ): void {
        $this->workerEvent->setState($startingState);
        $this->entityMutator->save($this->workerEvent);

        $event = $eventCreator($this->workerEvent);

        self::assertSame($startingState, $this->workerEvent->getState());

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertSame($expectedState, $this->workerEvent->getState());
    }

    /**
     * @return array<mixed>
     */
    public static function workerEventStateIsSetDataProvider(): array
    {
        return [
            'queued -> sending' => [
                'startingState' => WorkerEventState::QUEUED,
                'eventCreator' => function (WorkerEvent $workerEvent) {
                    return new SendingEvent($workerEvent);
                },
                'expectedState' => WorkerEventState::SENDING,
            ],
            'sending -> complete' => [
                'startingState' => WorkerEventState::SENDING,
                'eventCreator' => function (WorkerEvent $workerEvent) {
                    return new SentEvent($workerEvent);
                },
                'expectedState' => WorkerEventState::COMPLETE,
            ],
            'sending -> failed' => [
                'startingState' => WorkerEventState::SENDING,
                'eventCreator' => function (WorkerEvent $workerEvent) {
                    $message = new SendWebhookMessage(
                        new Subscriber('https://example.com', 'secret'),
                        new RemoteEvent(
                            NotifiableEventDeliveryEvent::REMOTE_EVENT_NAME,
                            (string) WorkerEventRemoteEventId::fromWorkerEvent($workerEvent),
                            [],
                        ),
                    );

                    $envelope = new Envelope($message);

                    return new WorkerMessageFailedEvent(
                        $envelope,
                        'receiver-name',
                        new \Exception(),
                    );
                },
                'expectedState' => WorkerEventState::FAILED,
            ],
        ];
    }
}
