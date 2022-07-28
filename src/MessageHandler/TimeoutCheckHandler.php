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

        $duration = time() - $job->getStartDateTime()->getTimestamp();
        if ($duration >= $job->maximumDurationInSeconds) {
            $this->eventDispatcher->dispatch(new JobTimeoutEvent(
                $job->label,
                $job->maximumDurationInSeconds
            ));
        } else {
            $this->timeoutCheckMessageDispatcher->dispatch();
        }
    }
}
