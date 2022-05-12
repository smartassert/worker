<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Services\WorkerEventStateMutator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliverEventMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @var EventHandlerInterface[]
     */
    private array $handlers = [];

    /**
     * @param iterable<EventHandlerInterface> $handlers
     */
    public function __construct(
        private MessageBusInterface $messageBus,
        private WorkerEventStateMutator $workerEventStateMutator,
        private readonly JobRepository $jobRepository,
        iterable $handlers
    ) {
        foreach ($handlers as $handler) {
            if ($handler instanceof EventHandlerInterface) {
                $this->handlers[] = $handler;
            }
        }
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

    public function dispatchForEvent(EventInterface $event): ?Envelope
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        foreach ($this->handlers as $handler) {
            if ($handler->handles($event)) {
                $workerEvent = $handler->createForEvent($job, $event);

                if ($workerEvent instanceof WorkerEvent) {
                    $this->dispatch($workerEvent);
                }
            }
        }

        return null;
    }

    private function dispatch(WorkerEvent $workerEvent): Envelope
    {
        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }
}
