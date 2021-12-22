<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Event\CallbackHttpErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CallbackHttpErrorEventSubscriber implements EventSubscriberInterface
{
    private ?CallbackHttpErrorEvent $event = null;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CallbackHttpErrorEvent::class => 'onCallbackHttpException',
        ];
    }

    public function onCallbackHttpException(CallbackHttpErrorEvent $event): void
    {
        $this->event = $event;
    }

    public function getEvent(): ?CallbackHttpErrorEvent
    {
        return $this->event;
    }
}
