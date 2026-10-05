<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\EventDelivery\SendingEvent;
use App\Event\EventDelivery\SentEvent;
use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\Model\SerializableEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\AddEventClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeliverEventHandler
{
    public function __construct(
        private JobRepository $jobRepository,
        private WorkerEventRepository $workerEventRepository,
        private AddEventClientInterface $resultsClient,
        private EventDispatcherInterface $eventDispatcher,
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

        $this->eventDispatcher->dispatch(new SendingEvent($eventEntity));

        try {
            $this->resultsClient->add($job->getEventNotifyUrl(), $notifiableEvent);
        } catch (\Throwable $e) {
            throw new EventDeliveryException($eventEntity, $e);
        }

        $this->eventDispatcher->dispatch(new SentEvent($eventEntity));
    }
}
