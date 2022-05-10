<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use Symfony\Contracts\EventDispatcher\Event;

class WorkerEventFactory
{
    /**
     * @var EventHandlerInterface[]
     */
    private array $handlers = [];

    /**
     * @param array<mixed> $handlers
     */
    public function __construct(
        private readonly JobRepository $jobRepository,
        iterable $handlers
    ) {
        foreach ($handlers as $handler) {
            if ($handler instanceof EventHandlerInterface) {
                $this->handlers[] = $handler;
            }
        }
    }

    public function createForEvent(Event $event): ?WorkerEvent
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        foreach ($this->handlers as $handler) {
            if ($handler->handles($event)) {
                return $handler->createForEvent($job, $event);
            }
        }

        return null;
    }
}
