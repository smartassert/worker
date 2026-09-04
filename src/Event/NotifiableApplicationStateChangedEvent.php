<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Job;
use App\Model\SerializableApplicationStateInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type SerializedApplicationState from SerializableApplicationStateInterface
 */
class NotifiableApplicationStateChangedEvent extends Event implements NotifiableEventInterface
{
    public const string REMOTE_EVENT_NAME = 'worker.application.state_changed';

    public function __construct(
        private readonly ?Job $job,
        private readonly ApplicationStateChangedEvent $event,
    ) {}

    public function getNotifyUrl(): ?string
    {
        $baseUrl = $this->job?->getStateNotifyUrl();
        if (null === $baseUrl) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . $this->getRemoteEventName();
    }

    public function getRemoteEventName(): string
    {
        return self::REMOTE_EVENT_NAME;
    }

    /**
     * @return array{
     *     previous_state: SerializedApplicationState,
     *     new_state: SerializedApplicationState,
     * }
     */
    public function getPayload(): array
    {
        return [
            'previous_state' => $this->event->previousState->toArray(),
            'new_state' => $this->event->newState->toArray(),
        ];
    }
}
