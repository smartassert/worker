<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Message\SendCallbackMessage;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\CallbackSetup;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
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
    private WorkerEvent $callback;

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
            ->withCallbackSetups([
                (new CallbackSetup())
                    ->withState(WorkerEvent::STATE_QUEUED),
            ]));

        $callbacks = $environment->getCallbacks();
        $callback = $callbacks[0];
        self::assertInstanceOf(WorkerEvent::class, $callback);

        $this->callback = $callback;
    }

    /**
     * @dataProvider handleEventDataProvider
     *
     * @param callable(WorkerEvent): WorkerMessageFailedEvent $eventCreator
     */
    public function testHandleEvent(callable $eventCreator, string $expectedCallbackState): void
    {
        self::assertSame(WorkerEvent::STATE_QUEUED, $this->callback->getState());

        $this->eventDispatcher->dispatch(
            $eventCreator($this->callback)
        );

        self::assertSame($expectedCallbackState, $this->callback->getState());
    }

    /**
     * @return array<mixed>
     */
    public function handleEventDataProvider(): array
    {
        return [
            'non-retryable due to unrecoverable exception' => [
                'eventCreator' => function (WorkerEvent $callback): WorkerMessageFailedEvent {
                    $message = new SendCallbackMessage((int) $callback->getId());
                    $envelope = new Envelope($message);

                    return new WorkerMessageFailedEvent(
                        $envelope,
                        'callback',
                        new UnrecoverableMessageHandlingException()
                    );
                },
                'expectedCallbackState' => WorkerEvent::STATE_FAILED,
            ],
            'non-retryable due to retry attempt exhaustion' => [
                'eventCreator' => function (WorkerEvent $callback): WorkerMessageFailedEvent {
                    $message = new SendCallbackMessage((int) $callback->getId());
                    $envelope = new Envelope($message, [
                        new RedeliveryStamp(3)
                    ]);

                    return new WorkerMessageFailedEvent(
                        $envelope,
                        'callback',
                        new \RuntimeException('Unfortunate event')
                    );
                },
                'expectedCallbackState' => WorkerEvent::STATE_FAILED,
            ],
        ];
    }
}
