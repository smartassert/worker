<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\ApplicationState;
use App\Enum\WorkerEventOutcome;
use App\Event\JobEvent;
use App\Exception\JobNotFoundException;
use App\Message\JobCompletedCheckMessage;
use App\Repository\JobRepository;
use App\Services\ApplicationProgress;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class JobCompletedCheckHandler implements MessageHandlerInterface
{
    public function __construct(
        private ApplicationProgress $applicationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private JobRepository $jobRepository,
    ) {
    }

    /**
     * @throws JobNotFoundException
     */
    public function __invoke(JobCompletedCheckMessage $jobCompleteCheckMessage): void
    {
        if ($this->applicationProgress->is(ApplicationState::COMPLETE)) {
            $job = $this->jobRepository->get();

            $this->eventDispatcher->dispatch(new JobEvent($job->getLabel(), WorkerEventOutcome::COMPLETED));
        }
    }
}
