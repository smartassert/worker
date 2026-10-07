<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EmittableEvent\EmittableEventInterface;
use App\Model\SerializableApplicationStateInterface;
use App\Model\SerializableEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type SerializedApplicationState from SerializableApplicationStateInterface
 */
class NotifiableEventDeliveryEvent extends Event implements NotifiableEventInterface
{
    public const string REMOTE_EVENT_NAME = 'worker.event';

    public function __construct(
        public readonly Job $job,
        private readonly WorkerEvent $workerEvent,
    ) {}

    public function getWorkerEvent(): WorkerEvent
    {
        return $this->workerEvent;
    }

    public function getNotifyUrl(): ?string
    {
        return $this->job->getEventNotifyUrl();
    }

    public function getRemoteEventName(): string
    {
        return self::REMOTE_EVENT_NAME;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return new SerializableEvent($this->job->getLabel(), $this->workerEvent)->toArray();
    }
}
