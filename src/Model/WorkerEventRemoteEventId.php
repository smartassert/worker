<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\WorkerEvent;

final readonly class WorkerEventRemoteEventId implements \Stringable
{
    private const string PREFIX = 'worker_event:';
    private const string ID_PATTERN = '/^' . self::PREFIX . '\d+$/';

    private function __construct(
        private int $workerEventId,
    ) {}

    public function __toString(): string
    {
        return sprintf('worker_event:%d', $this->workerEventId);
    }

    public static function fromWorkerEvent(WorkerEvent $workerEvent): self
    {
        return new self($workerEvent->getId());
    }

    public static function fromString(string $remoteEventId): ?self
    {
        if (1 !== preg_match(self::ID_PATTERN, $remoteEventId)) {
            return null;
        }

        $remoteEventId = (int) str_replace(self::PREFIX, '', $remoteEventId);

        return new self($remoteEventId);
    }

    public function getWorkerEventId(): int
    {
        return $this->workerEventId;
    }
}
