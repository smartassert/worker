<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\SourceCompilation\FailedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CompilationFailedEventCallbackFactory extends AbstractCompilationEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof FailedEvent;
    }

    public function createForEvent(Event $event): ?CallbackInterface
    {
        if ($event instanceof FailedEvent) {
            return $this->create(
                CallbackInterface::TYPE_COMPILATION_FAILED,
                '',
                $this->createPayload($event, [
                    'output' => $event->getOutput()->getData(),
                ])
            );
        }

        return null;
    }
}
