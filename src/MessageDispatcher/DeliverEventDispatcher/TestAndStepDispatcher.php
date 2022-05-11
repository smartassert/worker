<?php

declare(strict_types=1);

namespace App\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\StepEventInterface;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestEventInterface;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory\EventHandler\TestAndStepEventHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class TestAndStepDispatcher extends AbstractDeliverEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        DeliverEventMessageDispatcher $messageDispatcher,
        JobRepository $jobRepository,
        private readonly TestAndStepEventHandler $handler,
    ) {
        parent::__construct($messageDispatcher, $jobRepository);
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestStartedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            TestPassedEvent::class => [
                ['dispatchForEvent', 100],
            ],
            TestFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepPassedEvent::class => [
                ['dispatchForEvent', 0],
            ],
            StepFailedEvent::class => [
                ['dispatchForEvent', 0],
            ],
        ];
    }

    protected function createWorkerEvent(Job $job, Event $event): ?WorkerEvent
    {
        return $event instanceof TestEventInterface || $event instanceof StepEventInterface
            ? $this->handler->createForEvent($job, $event)
            : null;
    }
}
