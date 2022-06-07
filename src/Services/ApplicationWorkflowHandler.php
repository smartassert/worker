<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApplicationState;
use App\Enum\WorkerEventType;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\TestEvent;
use App\Event\TestPassedEvent;
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
            TestPassedEvent::class => [
                ['dispatchJobCompletedEvent', -100],
            ],
            TestEvent::class => [
                ['dispatchJobFailedEvent', -100],
            ],
        ];
    }

    public function dispatchJobCompletedEvent(TestPassedEvent $testEvent): void
    {
        if ($this->applicationProgress->is(ApplicationState::COMPLETE)) {
            $this->eventDispatcher->dispatch(new JobCompletedEvent());
        } else {
            $this->messageBus->dispatch(new JobCompletedCheckMessage());
        }
    }

    public function dispatchJobFailedEvent(TestEvent $event): void
    {
        if (WorkerEventType::TEST_FAILED !== $event->getType()) {
            return;
        }

        $this->eventDispatcher->dispatch(new JobFailedEvent());
    }
}
