<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Event\EmittableEvent\JobStartedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventRecorder implements EventSubscriberInterface
{
    /**
     * @var Event[]
     */
    private array $events = [];

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobStartedEvent::class => [
                ['addEvent', 1000],
            ],
        ];
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    public function getLatest(): ?Event
    {
        $latest = $this->events[0] ?? null;

        return $latest instanceof Event ? $latest : null;
    }
}
