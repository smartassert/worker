<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
use App\Event\StepEventInterface;
use App\Event\TestEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

class TestAndStepEventCallbackFactory extends AbstractEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof TestEventInterface || $event instanceof StepEventInterface;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackEntity
    {
        if ($event instanceof TestEventInterface || $event instanceof StepEventInterface) {
            return $this->create($job, $event, $event->getDocument()->getData());
        }

        return null;
    }
}
