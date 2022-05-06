<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackEntity;
use Doctrine\ORM\EntityManagerInterface;

class CallbackStateMutator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setQueued(CallbackEntity $callback): void
    {
        if (in_array($callback->getState(), [CallbackEntity::STATE_AWAITING, CallbackEntity::STATE_SENDING])) {
            $this->set($callback, CallbackEntity::STATE_QUEUED);
        }
    }

    public function setSending(CallbackEntity $callback): void
    {
        if (CallbackEntity::STATE_QUEUED === $callback->getState()) {
            $this->set($callback, CallbackEntity::STATE_SENDING);
        }
    }

    public function setFailed(CallbackEntity $callback): void
    {
        if (in_array($callback->getState(), [CallbackEntity::STATE_QUEUED, CallbackEntity::STATE_SENDING])) {
            $this->set($callback, CallbackEntity::STATE_FAILED);
        }
    }

    public function setComplete(CallbackEntity $callback): void
    {
        if (CallbackEntity::STATE_SENDING === $callback->getState()) {
            $this->set($callback, CallbackEntity::STATE_COMPLETE);
        }
    }

    /**
     * @param CallbackEntity::STATE_* $state
     */
    private function set(CallbackEntity $callback, string $state): void
    {
        $callback->setState($state);

        $this->entityManager->persist($callback->getEntity());
        $this->entityManager->flush();
    }
}
