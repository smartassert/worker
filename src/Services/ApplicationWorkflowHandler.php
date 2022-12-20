<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobEndState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEvent\JobEndedEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Event\JobEndStateChangeEvent;
use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplicationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private readonly JobRepository $jobRepository,
        private readonly JobCompleteEventDispatcher $jobCompleteEventDispatcher,
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
            ],
            JobEndStateChangeEvent::class => [
                ['dispatchJobEndedEventForJobEndStateChangeEvent', -100],
            ],
        ];
    }

    public function dispatchJobCompletedEventForTestPassedEvent(TestEvent $event): void
    {
        if (!(WorkerEventScope::TEST === $event->getScope() && WorkerEventOutcome::PASSED === $event->getOutcome())) {
            return;
        }

        $this->jobCompleteEventDispatcher->dispatch();
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchJobEndedEventForJobEndStateChangeEvent(JobEndStateChangeEvent $event): void
    {
        $job = $this->jobRepository->get();

        if (null === $job->endState) {
            return;
        }

        $jobEndedEvent = new JobEndedEvent(
            $job->label,
            $job->endState,
            JobEndState::COMPLETE === $job->endState,
        );

        $this->eventDispatcher->dispatch($jobEndedEvent);
    }
}
