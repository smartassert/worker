<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\WorkerEvent;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Message\DeliverEventMessage;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSender;
use App\Services\WorkerEventStateMutator;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SendCallbackHandler implements MessageHandlerInterface
{
    public function __construct(
        private WorkerEventRepository $repository,
        private WorkerEventSender $sender,
        private WorkerEventStateMutator $workerEventStateMutator
    ) {
    }

    /**
     * @throws NonSuccessfulHttpResponseException
     * @throws ClientExceptionInterface
     */
    public function __invoke(DeliverEventMessage $message): void
    {
        $workerEvent = $this->repository->find($message->getWorkerEventId());

        if ($workerEvent instanceof WorkerEvent) {
            $this->workerEventStateMutator->setSending($workerEvent);
            $this->sender->send($workerEvent);
            $this->workerEventStateMutator->setComplete($workerEvent);
        }
    }
}
