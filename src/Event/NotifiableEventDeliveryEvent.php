<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Job;
use App\Event\EmittableEvent\EmittableEventInterface;
use App\Model\SerializableApplicationStateInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type SerializedApplicationState from SerializableApplicationStateInterface
 */
class NotifiableEventDeliveryEvent extends Event implements NotifiableEventInterface
{
    public const string REMOTE_EVENT_NAME = 'worker.event';

    public function __construct(
        private readonly Job $job,
        private readonly EmittableEventInterface $event,
    ) {}

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
        return $this->event->getPayload();
    }
}
