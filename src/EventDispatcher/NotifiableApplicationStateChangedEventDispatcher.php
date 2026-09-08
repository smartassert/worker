<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\ApplicationStateChangedEvent;
use App\Services\NotifiableApplicationStateChangedEventFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class NotifiableApplicationStateChangedEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private NotifiableApplicationStateChangedEventFactory $factory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationStateChangedEvent::class => [
                ['dispatch', 1000],
            ],
        ];
    }

    public function dispatch(ApplicationStateChangedEvent $event): void
    {
        $notifiableEvent = $this->factory->create($event);

        $this->eventDispatcher->dispatch($notifiableEvent);
    }
}
