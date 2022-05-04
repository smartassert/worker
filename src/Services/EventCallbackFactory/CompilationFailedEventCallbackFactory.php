<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\SourceCompilation\FailedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationFailedEventCallbackFactory extends AbstractTestEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof FailedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof FailedEvent) {
            return $this->create(
                CallbackInterface::TYPE_COMPILATION_FAILED,
                $this->createCallbackReference($job, $event),
                $this->createPayload($event, [
                    'output' => $event->getOutput()->getData(),
                ])
            );
        }

        return null;
    }
}
