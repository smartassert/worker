<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory\EventFactoryInterface;
use Symfony\Contracts\EventDispatcher\Event;

class WorkerEventFactory
{
    /**
     * @var EventFactoryInterface[]
     */
    private array $eventCallbackFactories;

    /**
     * @param array<mixed> $handlers
     */
    public function __construct(
        private readonly JobRepository $jobRepository,
        iterable $handlers
    ) {
        $filteredHandlers = [];

        foreach ($handlers as $handler) {
            if ($handler instanceof EventFactoryInterface) {
                $filteredHandlers[] = $handler;
            }
        }

        $this->eventCallbackFactories = $filteredHandlers;
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
