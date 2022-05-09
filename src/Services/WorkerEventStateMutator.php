<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use Doctrine\ORM\EntityManagerInterface;

class WorkerEventStateMutator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setQueued(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEvent::STATE_AWAITING, WorkerEvent::STATE_SENDING])) {
            $this->set($workerEvent, WorkerEvent::STATE_QUEUED);
        }
    }

    public function setSending(WorkerEvent $workerEvent): void
    {
        if (WorkerEvent::STATE_QUEUED === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEvent::STATE_SENDING);
        }
    }

    public function setFailed(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEvent::STATE_QUEUED, WorkerEvent::STATE_SENDING])) {
            $this->set($workerEvent, WorkerEvent::STATE_FAILED);
        }
    }

    public function setComplete(WorkerEvent $workerEvent): void
    {
        if (WorkerEvent::STATE_SENDING === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEvent::STATE_COMPLETE);
        }
    }

    /**
     * @param WorkerEvent::STATE_* $state
     */
    private function set(WorkerEvent $workerEvent, string $state): void
    {
        $workerEvent->setState($state);

        $this->entityManager->persist($workerEvent->getEntity());
        $this->entityManager->flush();
    }
}
