<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
use App\Event\SourceCompilation\FailedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationFailedEventCallbackFactory extends AbstractCompilationEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof FailedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackEntity
    {
        if ($event instanceof FailedEvent) {
            return $this->create($job, $event, $this->createPayload($event, [
                'output' => $event->getOutput()->getData(),
            ]));
        }

        return null;
    }
}
