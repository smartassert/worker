<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\ApplicationStateChangedEvent;
use App\Event\NotifiableApplicationStateChangedEvent;
use App\Repository\JobRepository;

readonly class NotifiableApplicationStateChangedEventFactory
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    public function create(ApplicationStateChangedEvent $event): ?NotifiableApplicationStateChangedEvent
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        return new NotifiableApplicationStateChangedEvent($job, $event);
    }
}
