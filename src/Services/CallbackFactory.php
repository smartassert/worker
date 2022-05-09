<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Repository\JobRepository;
use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CallbackFactory
{
    /**
     * @var EventCallbackFactoryInterface[]
     */
    private array $eventCallbackFactories;

    /**
     * @param array<mixed> $eventCallbackFactories
     */
    public function __construct(
        private readonly JobRepository $jobRepository,
        array $eventCallbackFactories
    ) {
        $this->eventCallbackFactories = array_filter($eventCallbackFactories, function ($item) {
            return $item instanceof EventCallbackFactoryInterface;
        });
    }

    public function createForEvent(Event $event): ?WorkerEvent
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        foreach ($this->eventCallbackFactories as $eventCallbackFactory) {
            if ($eventCallbackFactory->handles($event)) {
                return $eventCallbackFactory->createForEvent($job, $event);
            }
        }

        return null;
    }
}
