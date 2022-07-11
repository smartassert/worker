<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApplicationState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\JobEvent;
use App\Event\TestEvent;
use App\Exception\JobNotFoundException;
use App\Message\JobCompletedCheckMessage;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ApplicationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private ApplicationProgress $applicationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private JobRepository $jobRepository,
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
                ['dispatchJobFailedEventForTestFailureEvent', -100],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchJobCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        if ($this->applicationProgress->is(ApplicationState::COMPLETE)) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new JobEvent($job->getLabel(), WorkerEventOutcome::COMPLETED));
        } else {
            $this->messageBus->dispatch(new JobCompletedCheckMessage());
        }
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchJobFailedEventForTestFailureEvent(TestEvent $event): void
    {
        if (
            WorkerEventScope::TEST !== $event->getScope()
            || !in_array($event->getOutcome(), [WorkerEventOutcome::FAILED, WorkerEventOutcome::EXCEPTION])
        ) {
            return;
        }

        $job = $this->jobRepository->get();
        $this->eventDispatcher->dispatch(new JobEvent($job->getLabel(), WorkerEventOutcome::FAILED));
    }
}
