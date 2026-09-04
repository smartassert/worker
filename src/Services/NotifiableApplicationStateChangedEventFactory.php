<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\ApplicationStateChangedEvent;
use App\Event\NotifiableApplicationStateChangedEvent;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class NotifiableApplicationStateChangedEventFactory implements EventSubscriberInterface
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    /**
     * @return array<class-string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationStateChangedEvent::class => [
                ['createNotifiableApplicationStateChangedEvent', 1000],
            ],
        ];
    }

    /**
     * @throws JobNotFoundException
     */
    public function createNotifiableApplicationStateChangedEvent(
        ApplicationStateChangedEvent $event
    ): NotifiableApplicationStateChangedEvent {
        return new NotifiableApplicationStateChangedEvent($this->jobRepository->get(), $event);
    }
}
