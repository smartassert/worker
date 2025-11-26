<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Exception\JobNotFoundException;
use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

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
     * @throws ExceptionInterface
     */
    public function __invoke(TimeoutCheckMessage $timeoutCheck): void
    {
        $job = $this->jobRepository->get();

        $duration = time() - $job->startDateTime->getTimestamp();
        if ($duration >= $job->maximumDurationInSeconds) {
            $this->eventDispatcher->dispatch(new JobTimeoutEvent(
                $job->getLabel(),
                $job->maximumDurationInSeconds
            ));
        } else {
            $this->timeoutCheckMessageDispatcher->dispatch();
        }
    }
}
