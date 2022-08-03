<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Enum\ApplicationState;
use App\Enum\WorkerEventOutcome;
use App\Event\JobEvent;
use App\Exception\JobNotFoundException;
use App\MessageDispatcher\DelayedMessageDispatcher;
use App\Repository\JobRepository;
use App\Services\ApplicationProgress;
use Psr\EventDispatcher\EventDispatcherInterface;

class JobCompleteEventDispatcher
{
    public function __construct(
        private readonly ApplicationProgress $applicationProgress,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JobRepository $jobRepository,
        private readonly DelayedMessageDispatcher $jobCompletedCheckMessageDispatcher,
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
            $this->jobCompletedCheckMessageDispatcher->dispatch();
        }
    }
}
