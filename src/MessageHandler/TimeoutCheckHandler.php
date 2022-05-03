<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\JobTimeoutEvent;
use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Services\EntityStore\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;

class TimeoutCheckHandler
{
    public function __construct(
        private JobStore $jobStore,
        private TimeoutCheckMessageDispatcher $timeoutCheckMessageDispatcher,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(TimeoutCheckMessage $timeoutCheck): void
    {
        $job = $this->jobStore->get();
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
