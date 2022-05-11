<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\Test as TestEntity;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Entity\WorkerEventType;
use App\Event\JobTimeoutEvent;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

class JobTimeoutEventDeliverEventDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const JOB_LABEL = 'job_label_content';

    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(TestEntity::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        if ($environmentFactory instanceof EnvironmentFactory) {
            $environmentFactory->create(
                (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel(self::JOB_LABEL)
                    )
            );
        }
    }

    /**
     * @dataProvider createWorkerEventAndDispatchDeliverEventMessageDataProvider
     */
    public function testCreateWorkerEventAndDispatchDeliverEventMessage(
        Event $event,
        WorkerEvent $expectedWorkerEvent
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(1);

        $envelope = $this->messengerAsserter->getEnvelopeAtPosition(0);
        $message = $envelope->getMessage();
        self::assertInstanceOf(DeliverEventMessage::class, $message);

        $workerEvent = $this->workerEventRepository->find($message->workerEventId);
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame(WorkerEventState::QUEUED, $workerEvent->getState());
        self::assertSame($expectedWorkerEvent->getType(), $workerEvent->getType());
        self::assertSame($expectedWorkerEvent->getReference(), $workerEvent->getReference());
        self::assertSame($expectedWorkerEvent->getPayload(), $workerEvent->getPayload());
    }

    /**
     * @return array<mixed>
     */
    public function createWorkerEventAndDispatchDeliverEventMessageDataProvider(): array
    {
        return [
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_TIME_OUT,
                    md5(self::JOB_LABEL),
                    [
                        'maximum_duration_in_seconds' => 10,
                    ]
                ),
            ],
        ];
    }
}
