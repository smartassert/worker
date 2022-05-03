<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\JobTimeoutEvent;
use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

class TimeoutCheckHandler
{
    public function __construct(
        private JobRepository $jobRepository,
        private TimeoutCheckMessageDispatcher $timeoutCheckMessageDispatcher,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(TimeoutCheckMessage $timeoutCheck): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        if ($job->hasReachedMaximumDuration()) {
            $this->eventDispatcher->dispatch(new JobTimeoutEvent($job->getMaximumDurationInSeconds()));
        } else {
            $this->timeoutCheckMessageDispatcher->dispatch();
        }
    }
}
