<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventState;
use App\Message\DeliverEventMessage;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class WorkerMessageFailedEventSubscriberTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private WorkerEvent $workerEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $eventListenerRemover->remove([
            'messenger.retry.send_failed_message_for_retry_listener' => [
                WorkerMessageFailedEvent::class => ['onMessageFailed'],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $environment = $environmentFactory->create((new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withWorkerEventSetups([
                (new WorkerEventSetup())
                    ->withState(WorkerEventState::QUEUED),
            ]));

        $workerEvents = $environment->getWorkerEvents();
        $workerEvent = $workerEvents[0];
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);

        $this->workerEvent = $workerEvent;
    }

    /**
     * @dataProvider handleEventDataProvider
     *
     * @param callable(WorkerEvent): WorkerMessageFailedEvent $eventCreator
     */
    public function testHandleEvent(callable $eventCreator, WorkerEventState $expectedWorkerEventState): void
    {
        self::assertSame(WorkerEventState::QUEUED, $this->workerEvent->getState());

        $this->eventDispatcher->dispatch(
            $eventCreator($this->workerEvent)
        );

        self::assertSame($expectedWorkerEventState, $this->workerEvent->getState());
    }

    /**
     * @return array<mixed>
     */
    public function handleEventDataProvider(): array
    {
        return [
            'non-retryable due to unrecoverable exception' => [
                'eventCreator' => function (WorkerEvent $workerEvent): WorkerMessageFailedEvent {
                    $message = new DeliverEventMessage((int) $workerEvent->getId());
                    $envelope = new Envelope($message);

                    return new WorkerMessageFailedEvent(
                        $envelope,
                        'event_delivery',
                        new UnrecoverableMessageHandlingException()
                    );
                },
                'expectedWorkerEventState' => WorkerEventState::FAILED,
            ],
            'non-retryable due to retry attempt exhaustion' => [
                'eventCreator' => function (WorkerEvent $workerEvent): WorkerMessageFailedEvent {
                    $message = new DeliverEventMessage((int) $workerEvent->getId());
                    $envelope = new Envelope($message, [
                        new RedeliveryStamp(3)
                    ]);

                    return new WorkerMessageFailedEvent(
                        $envelope,
                        'callevent_deliveryback',
                        new \RuntimeException('Unfortunate event')
                    );
                },
                'expectedWorkerEventState' => WorkerEventState::FAILED,
            ],
        ];
    }
}
