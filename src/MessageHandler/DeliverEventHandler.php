<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\WorkerEvent;
use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventStateMutator;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeliverEventHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly WorkerEventStateMutator $workerEventStateMutator,
        private readonly ResultsClient $resultsClient,
    ) {}

    /**
     * @throws EventDeliveryException
     */
    public function __invoke(DeliverEventMessage $message): void
    {
        $workerEvent = $this->workerEventRepository->find($message->workerEventId);

        if ($workerEvent instanceof WorkerEvent) {
            $this->workerEventStateMutator->setSending($workerEvent);

            try {
                $this->resultsClient->addEvent($this->jobRepository->get()->getResultsToken(), $workerEvent);
            } catch (\Throwable $e) {
                throw new EventDeliveryException($workerEvent, $e);
            }

            $this->workerEventStateMutator->setComplete($workerEvent);
        }
    }
}
