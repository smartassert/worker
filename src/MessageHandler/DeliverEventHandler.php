<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\WorkerEvent;
use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSender;
use App\Services\WorkerEventStateMutator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeliverEventHandler
{
    public function __construct(
        private readonly WorkerEventRepository $repository,
        private readonly WorkerEventSender $sender,
        private readonly WorkerEventStateMutator $workerEventStateMutator
    ) {
    }

    /**
     * @throws EventDeliveryException
     */
    public function __invoke(DeliverEventMessage $message): void
    {
        $workerEvent = $this->repository->find($message->workerEventId);

        if ($workerEvent instanceof WorkerEvent) {
            $this->workerEventStateMutator->setSending($workerEvent);

            try {
                $this->sender->send($workerEvent);
            } catch (\Throwable $e) {
                throw new EventDeliveryException($workerEvent, $e);
            }

            $this->workerEventStateMutator->setComplete($workerEvent);
        }
    }
}
