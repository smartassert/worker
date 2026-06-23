<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Event\EmittableEvent\CompilationFailedEvent;
use App\Event\EmittableEvent\CompilationPassedEvent;
use App\Event\EmittableEvent\CompilationStartedEvent;
use App\Event\EmittableEvent\CompilationTimedOutEvent;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EmittableEvent\JobTimeoutEvent;
use App\Event\EmittableEvent\StepEvent;
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
            CompilationStartedEvent::class => [
                ['addEvent', 1000],
            ],
            CompilationPassedEvent::class => [
                ['addEvent', 1000],
            ],
            CompilationFailedEvent::class => [
                ['addEvent', 1000],
            ],
            CompilationTimedOutEvent::class => [
                ['addEvent', 1000],
            ],
            TestEvent::class => [
                ['addEvent', 1000],
            ],
            JobTimeoutEvent::class => [
                ['addEvent', 1000],
            ],
            StepEvent::class => [
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
