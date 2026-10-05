<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\Model\SerializableEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventStateMutator;
use SmartAssert\ResultsClient\AddEventClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeliverEventHandler
{
    public function __construct(
        private JobRepository $jobRepository,
        private WorkerEventRepository $workerEventRepository,
        private WorkerEventStateMutator $workerEventStateMutator,
        private AddEventClientInterface $resultsClient,
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

        $eventEntity = $this->workerEventRepository->find($message->workerEventId);
        if (null === $eventEntity) {
            return;
        }

        $notifiableEvent = new SerializableEvent($job->getLabel(), $eventEntity);

        $this->workerEventStateMutator->setSending($eventEntity);

        try {
            $this->resultsClient->add($job->getEventNotifyUrl(), $notifiableEvent);
        } catch (\Throwable $e) {
            throw new EventDeliveryException($eventEntity, $e);
        }

        $this->workerEventStateMutator->setComplete($eventEntity);
    }
}
