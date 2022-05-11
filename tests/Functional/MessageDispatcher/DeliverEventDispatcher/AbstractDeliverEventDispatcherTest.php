<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDeliverEventDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    protected const JOB_LABEL = 'job_label_content';

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
            foreach ($this->getEntityClassNamesToRemove() as $entityClassName) {
                $entityRemover->removeForEntity($entityClassName);
            }
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        if ($environmentFactory instanceof EnvironmentFactory) {
            $environmentFactory->create($this->getEnvironmentSetup());
        }
    }

    /**
     * @dataProvider createWorkerEventAndDispatchDeliverEventMessageDataProvider
     */
    public function testCreateWorkerEventAndDispatchDeliverEventMessage(
        callable $eventCreator,
        WorkerEvent $expectedWorkerEvent
    ): void {
        $event = $eventCreator(...$this->getEventCreatorArguments());
        self::assertInstanceOf(Event::class, $event);

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
     * @return class-string[]
     */
    abstract protected function getEntityClassNamesToRemove(): array;

    abstract protected function getEnvironmentSetup(): EnvironmentSetup;

    /**
     * @return array<mixed>
     */
    abstract protected function createWorkerEventAndDispatchDeliverEventMessageDataProvider(): array;

    /**
     * @return array<mixed>
     */
    abstract protected function getEventCreatorArguments(): array;
}
