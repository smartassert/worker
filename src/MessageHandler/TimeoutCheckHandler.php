<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\JobTimeoutEvent;
use App\Exception\JobNotFoundException;
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

    /**
     * @throws JobNotFoundException
     */
    public function __invoke(TimeoutCheckMessage $timeoutCheck): void
    {
        $job = $this->jobRepository->get();

        if ($job->hasReachedMaximumDuration()) {
            $this->eventDispatcher->dispatch(new JobTimeoutEvent(
                $job->getLabel(),
                $job->getMaximumDurationInSeconds()
            ));
        } else {
            $this->timeoutCheckMessageDispatcher->dispatch();
        }
    }
}
