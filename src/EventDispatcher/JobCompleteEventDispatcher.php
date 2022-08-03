<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Enum\ApplicationState;
use App\Enum\WorkerEventOutcome;
use App\Event\JobEvent;
use App\Exception\JobNotFoundException;
use App\Message\JobCompletedCheckMessage;
use App\Messenger\MessageFactory;
use App\Repository\JobRepository;
use App\Services\ApplicationProgress;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class JobCompleteEventDispatcher
{
    public function __construct(
        private readonly ApplicationProgress $applicationProgress,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JobRepository $jobRepository,
        private readonly MessageFactory $messageFactory,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatch(): void
    {
        if ($this->applicationProgress->is([ApplicationState::COMPLETE])) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new JobEvent($job->label, WorkerEventOutcome::COMPLETED));
        } else {
            $this->messageBus->dispatch(
                $this->messageFactory->createDelayedEnvelope(new JobCompletedCheckMessage())
            );
        }
    }
}
