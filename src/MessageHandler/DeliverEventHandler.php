<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventStateMutator;
use SmartAssert\ResultsClient\AddEventClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeliverEventHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly WorkerEventStateMutator $workerEventStateMutator,
        private readonly AddEventClientInterface $resultsClient,
    ) {}

    /**
     * @throws EventDeliveryException
     */
    public function __invoke(DeliverEventMessage $message): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        $workerEvent = $this->workerEventRepository->find($message->workerEventId);
        if (null === $workerEvent) {
            return;
        }

        $this->workerEventStateMutator->setSending($workerEvent);

        try {
            $this->resultsClient->add($job->getEventAddUrl(), $workerEvent);
        } catch (\Throwable $e) {
            throw new EventDeliveryException($workerEvent, $e);
        }

        $this->workerEventStateMutator->setComplete($workerEvent);
    }
}
