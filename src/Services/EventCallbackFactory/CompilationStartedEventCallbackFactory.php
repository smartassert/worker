<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\SourceCompilation\StartedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationStartedEventCallbackFactory extends AbstractCompilationEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof StartedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof StartedEvent) {
            return $this->create(
                CallbackInterface::TYPE_COMPILATION_STARTED,
                $this->callbackReferenceFactory->createForEvent($job, $event),
                $this->createPayload($event)
            );
        }

        return null;
    }
}
