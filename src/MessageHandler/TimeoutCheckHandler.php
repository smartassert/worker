<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\JobEndedState;
use App\Event\JobTimeoutEvent;
use App\Exception\JobNotFoundException;
use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TimeoutCheckHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly TimeoutCheckMessageDispatcher $timeoutCheckMessageDispatcher,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws JobNotFoundException
     */
    public function __invoke(TimeoutCheckMessage $timeoutCheck): void
    {
        $job = $this->jobRepository->get();

        $duration = time() - $job->startDateTime->getTimestamp();
        if ($duration >= $job->maximumDurationInSeconds) {
            $job->setEndState(JobEndedState::TIMED_OUT);
            $this->jobRepository->add($job);

            $this->eventDispatcher->dispatch(new JobTimeoutEvent(
                $job->label,
                $job->maximumDurationInSeconds
            ));
        } else {
            $this->timeoutCheckMessageDispatcher->dispatch();
        }
    }
}
