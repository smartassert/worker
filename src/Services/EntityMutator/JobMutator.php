<?php

declare(strict_types=1);

namespace App\Services\EntityMutator;

use App\Entity\Job;
use App\Event\ApplicationStateChangedEvent;
use App\Repository\JobRepository;
use App\Services\ApplicationStateFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class JobMutator
{
    public function __construct(
        private JobRepository $jobRepository,
        private ApplicationStateFactory $applicationStateFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function save(Job $job): void
    {
        $preSaveState = $this->applicationStateFactory->create();
        $this->jobRepository->add($job);
        $postSaveState = $this->applicationStateFactory->create();

        if (false === $postSaveState->equals($preSaveState)) {
            $this->eventDispatcher->dispatch(
                new ApplicationStateChangedEvent($preSaveState, $postSaveState)
            );
        }
    }
}
