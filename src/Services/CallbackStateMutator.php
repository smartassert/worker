<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use Doctrine\ORM\EntityManagerInterface;

class CallbackStateMutator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setQueued(WorkerEvent $callback): void
    {
        if (in_array($callback->getState(), [WorkerEvent::STATE_AWAITING, WorkerEvent::STATE_SENDING])) {
            $this->set($callback, WorkerEvent::STATE_QUEUED);
        }
    }

    public function setSending(WorkerEvent $callback): void
    {
        if (WorkerEvent::STATE_QUEUED === $callback->getState()) {
            $this->set($callback, WorkerEvent::STATE_SENDING);
        }
    }

    public function setFailed(WorkerEvent $callback): void
    {
        if (in_array($callback->getState(), [WorkerEvent::STATE_QUEUED, WorkerEvent::STATE_SENDING])) {
            $this->set($callback, WorkerEvent::STATE_FAILED);
        }
    }

    public function setComplete(WorkerEvent $callback): void
    {
        if (WorkerEvent::STATE_SENDING === $callback->getState()) {
            $this->set($callback, WorkerEvent::STATE_COMPLETE);
        }
    }

    /**
     * @param WorkerEvent::STATE_* $state
     */
    private function set(WorkerEvent $callback, string $state): void
    {
        $callback->setState($state);

        $this->entityManager->persist($callback->getEntity());
        $this->entityManager->flush();
    }
}
