<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\SourceCompilation\PassedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationPassedEventCallbackFactory extends AbstractTestEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof PassedEvent;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof PassedEvent) {
            return $this->create(
                CallbackInterface::TYPE_COMPILATION_PASSED,
                $this->createCallbackReference($job, $event),
                $this->createPayload($event)
            );
        }

        return null;
    }
}
