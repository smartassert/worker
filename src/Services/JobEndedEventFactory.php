<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobEndState;
use App\Event\EmittableEvent\JobEndedEvent;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;

class JobEndedEventFactory
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerEventRepository $workerEventRepository,
    ) {}

    /**
     * @throws JobNotFoundException
     */
    public function create(): ?JobEndedEvent
    {
        $job = $this->jobRepository->get();
        if (null === $job->endState) {
            return null;
        }

        $eventCount = $this->workerEventRepository->count([]);

        return new JobEndedEvent(
            $job->getLabel(),
            $job->endState,
            JobEndState::COMPLETE === $job->endState,
            $eventCount + 1
        );
    }
}
