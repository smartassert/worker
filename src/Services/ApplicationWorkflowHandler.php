<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\TestFailedEvent;
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
                ['dispatchJobCompletedEvent', 0],
            ],
            TestFailedEvent::class => [
                ['dispatchJobFailedEvent', 0],
            ],
        ];
    }

    public function dispatchJobCompletedEvent(TestPassedEvent $testEvent): void
    {
        if ($this->applicationProgress->is(ApplicationProgress::STATE_COMPLETE)) {
            $this->eventDispatcher->dispatch(new JobCompletedEvent());
        } else {
            $this->messageBus->dispatch(new JobCompletedCheckMessage());
        }
    }

    public function dispatchJobFailedEvent(TestFailedEvent $testEvent): void
    {
        $this->eventDispatcher->dispatch(new JobFailedEvent());
    }
}
