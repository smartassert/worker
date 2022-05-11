<?php

declare(strict_types=1);

namespace App\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Repository\JobRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDeliverEventDispatcher
{
    public function __construct(
        private readonly DeliverEventMessageDispatcher $messageDispatcher,
        private readonly JobRepository $jobRepository,
    ) {
    }

    public function dispatchForEvent(Event $event): ?Envelope
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        $workerEvent = $this->createWorkerEvent($job, $event);

        return $workerEvent instanceof WorkerEvent ? $this->messageDispatcher->dispatch($workerEvent) : null;
    }

    abstract protected function createWorkerEvent(Job $job, Event $event): ?WorkerEvent;
}
