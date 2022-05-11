<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use Doctrine\ORM\EntityManagerInterface;

class WorkerEventStateMutator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setQueued(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEventState::AWAITING, WorkerEventState::SENDING])) {
            $this->set($workerEvent, WorkerEventState::QUEUED);
        }
    }

    public function setSending(WorkerEvent $workerEvent): void
    {
        if (WorkerEventState::QUEUED === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEventState::SENDING);
        }
    }

    public function setFailed(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEventState::QUEUED, WorkerEventState::SENDING])) {
            $this->set($workerEvent, WorkerEventState::FAILED);
        }
    }

    public function setComplete(WorkerEvent $workerEvent): void
    {
        if (WorkerEventState::SENDING === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEventState::COMPLETE);
        }
    }

    private function set(WorkerEvent $workerEvent, WorkerEventState $state): void
    {
        $workerEvent->setState($state);

        $this->entityManager->persist($workerEvent);
        $this->entityManager->flush();
    }
}
