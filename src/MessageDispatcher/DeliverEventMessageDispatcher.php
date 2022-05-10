<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\WorkerEvent;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilation\FailedEvent as SourceCompilationFailedEvent;
use App\Event\SourceCompilation\PassedEvent as SourceCompilationPassedEvent;
use App\Event\SourceCompilation\StartedEvent as SourceCompilationStartedEvent;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Message\DeliverEventMessage;
use App\Services\WorkerEventFactory\WorkerEventFactory;
use App\Services\WorkerEventStateMutator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\Event;

class DeliverEventMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private WorkerEventStateMutator $workerEventStateMutator,
        private WorkerEventFactory $workerEventFactory
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobReadyEvent::class => [
                ['dispatchForEvent', 500],
            ],
            SourceCompilationStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            SourceCompilationPassedEvent::class => [
                ['dispatchForEvent', 500],
            ],
            SourceCompilationFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobCompiledEvent::class => [
                ['dispatchForEvent', 100],
            ],
            ExecutionStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            ExecutionCompletedEvent::class => [
                ['dispatchForEvent', 50],
            ],
            JobTimeoutEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobCompletedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepPassedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestPassedEvent::class => [
                ['dispatchForEvent', 100],
            ],
            TestFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            JobFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    public function dispatchForEvent(Event $event): ?Envelope
    {
        $workerEvent = $this->workerEventFactory->createForEvent($event);
        if ($workerEvent instanceof WorkerEvent) {
            return $this->dispatch($workerEvent);
        }

        return null;
    }

    public function dispatch(WorkerEvent $workerEvent): Envelope
    {
        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }
}
