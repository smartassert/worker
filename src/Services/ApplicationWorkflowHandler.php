<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApplicationState;
use App\Enum\WorkerEventType;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\TestEvent;
use App\Message\JobCompletedCheckMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ApplicationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private ApplicationProgress $applicationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestEvent::class => [
                ['dispatchJobCompletedEventForTestPassedEvent', -100],
                ['dispatchJobFailedEventForTestFailedEvent', -100],
            ],
        ];
    }

    public function dispatchJobCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (WorkerEventType::TEST_PASSED !== $event->getType()) {
            return;
        }

        if ($this->applicationProgress->is(ApplicationState::COMPLETE)) {
            $this->eventDispatcher->dispatch(new JobCompletedEvent());
        } else {
            $this->messageBus->dispatch(new JobCompletedCheckMessage());
        }
    }

    public function dispatchJobFailedEventForTestFailedEvent(TestEvent $event): void
    {
        if (WorkerEventType::TEST_FAILED !== $event->getType()) {
            return;
        }

        $this->eventDispatcher->dispatch(new JobFailedEvent());
    }
}
