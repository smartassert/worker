<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEventState;
use App\Repository\WorkerEventRepository;

class EventDeliveryState
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_RUNNING = 'running';
    public const STATE_COMPLETE = 'complete';

    public function __construct(
        private readonly WorkerEventRepository $repository
    ) {
    }

    /**
     * @return self::STATE_*
     */
    public function get(): string
    {
        $eventCount = $this->repository->count([]);
        $finishedEventCount = $this->repository->count([
            'state' => [
                WorkerEventState::FAILED->value,
                WorkerEventState::COMPLETE->value,
            ],
        ]);

        if (0 === $eventCount) {
            return self::STATE_AWAITING;
        }

        return $finishedEventCount === $eventCount
            ? self::STATE_COMPLETE
            : self::STATE_RUNNING;
    }

    /**
     * @param EventDeliveryState::STATE_* ...$states
     */
    public function is(...$states): bool
    {
        $states = array_filter($states, function ($item) {
            return is_string($item);
        });

        return in_array($this->get(), $states);
    }
}
