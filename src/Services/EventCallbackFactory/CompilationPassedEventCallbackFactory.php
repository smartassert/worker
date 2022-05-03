<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\SourceCompilation\PassedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationPassedEventCallbackFactory extends AbstractCompilationEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof PassedEvent;
    }

    public function createForEvent(Event $event): ?CallbackInterface
    {
        if ($event instanceof PassedEvent) {
            return $this->create(
                CallbackInterface::TYPE_COMPILATION_PASSED,
                '',
                $this->createPayload($event)
            );
        }

        return null;
    }
}
