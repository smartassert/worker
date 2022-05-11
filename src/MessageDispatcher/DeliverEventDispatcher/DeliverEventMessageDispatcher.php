<?php

declare(strict_types=1);

namespace App\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\WorkerEvent;
use App\Message\DeliverEventMessage;
use App\Services\WorkerEventStateMutator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliverEventMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly WorkerEventStateMutator $workerEventStateMutator,
    ) {
    }

    public function dispatch(WorkerEvent $workerEvent): Envelope
    {
        $this->workerEventStateMutator->setQueued($workerEvent);

        return $this->messageBus->dispatch(new DeliverEventMessage((int) $workerEvent->getId()));
    }
}
