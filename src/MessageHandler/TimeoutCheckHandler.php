<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\JobTimeoutEvent;
use App\Exception\JobNotFoundException;
use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\DelayedMessageDispatcher;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class TimeoutCheckHandler implements MessageHandlerInterface
{
    public function __construct(
        private JobRepository $jobRepository,
        private DelayedMessageDispatcher $timeoutCheckMessageDispatcher,
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
            $this->eventDispatcher->dispatch(new JobTimeoutEvent(
                $job->label,
                $job->maximumDurationInSeconds
            ));
        } else {
            $this->timeoutCheckMessageDispatcher->dispatch();
        }
    }
}
