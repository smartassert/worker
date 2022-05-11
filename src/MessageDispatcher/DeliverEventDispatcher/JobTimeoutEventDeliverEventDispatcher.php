<?php

declare(strict_types=1);

namespace App\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\JobTimeoutEvent;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory\EventHandler\JobTimeoutEventHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class JobTimeoutEventDeliverEventDispatcher extends AbstractDeliverEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        DeliverEventMessageDispatcher $messageDispatcher,
        JobRepository $jobRepository,
        private readonly JobTimeoutEventHandler $handler,
    ) {
        parent::__construct($messageDispatcher, $jobRepository);
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobTimeoutEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    protected function createWorkerEvent(Job $job, Event $event): ?WorkerEvent
    {
        return $event instanceof JobTimeoutEvent ? $this->handler->createForEvent($job, $event) : null;
    }
}
