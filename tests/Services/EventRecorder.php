<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EmittableEvent\SourceCompilationFailedEvent;
use App\Event\EmittableEvent\SourceCompilationPassedEvent;
use App\Event\EmittableEvent\SourceCompilationStartedEvent;
use App\Event\EmittableEvent\TestEvent;
use App\Message\CompileSourceMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventRecorder implements EventSubscriberInterface
{
    /**
     * @var object[]
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
            CompileSourceMessage::class => [
                ['addEvent', 1000],
            ],
            SourceCompilationStartedEvent::class => [
                ['addEvent', 1000],
            ],
            SourceCompilationPassedEvent::class => [
                ['addEvent', 1000],
            ],
            SourceCompilationFailedEvent::class => [
                ['addEvent', 1000],
            ],
            TestEvent::class => [
                ['addEvent', 1000],
            ],
        ];
    }

    public function addEvent(object $event): void
    {
        $this->events[] = $event;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @param 0|positive-int $index
     */
    public function get(int $index): ?object
    {
        return $this->events[$index] ?? null;
    }
}
