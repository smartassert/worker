<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
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

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof TestEventInterface) {
            return $this->create($job, $event, $event->getDocument()->getData());
        }

        if ($event instanceof StepEventInterface) {
            $document = $event->getDocument();

            $documentData = $document->parse();
            $documentData = is_array($documentData) ? $documentData : [];

            return $this->create($job, $event, $documentData);
        }

        return null;
    }
}
