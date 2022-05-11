<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState as WorkerEventStateEnum;
use Doctrine\ORM\EntityManagerInterface;

class WorkerEventStateMutator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setQueued(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEventStateEnum::AWAITING, WorkerEventStateEnum::SENDING])) {
            $this->set($workerEvent, WorkerEventStateEnum::QUEUED);
        }
    }

    public function setSending(WorkerEvent $workerEvent): void
    {
        if (WorkerEventStateEnum::QUEUED === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEventStateEnum::SENDING);
        }
    }

    public function setFailed(WorkerEvent $workerEvent): void
    {
        if (in_array($workerEvent->getState(), [WorkerEventStateEnum::QUEUED, WorkerEventStateEnum::SENDING])) {
            $this->set($workerEvent, WorkerEventStateEnum::FAILED);
        }
    }

    public function setComplete(WorkerEvent $workerEvent): void
    {
        if (WorkerEventStateEnum::SENDING === $workerEvent->getState()) {
            $this->set($workerEvent, WorkerEventStateEnum::COMPLETE);
        }
    }

    private function set(WorkerEvent $workerEvent, WorkerEventStateEnum $state): void
    {
        $workerEvent->setState($state);

        $this->entityManager->persist($workerEvent);
        $this->entityManager->flush();
    }
}
