<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\EventDeliveryState;
use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;

class EventDeliveryProgress
{
    public function __construct(
        private readonly WorkerEventRepository $repository
    ) {
    }

    public function get(): EventDeliveryState
    {
        $eventCount = $this->repository->count([]);
        $finishedEventCount = $this->repository->count([
            'state' => [
                WorkerEventState::FAILED->value,
                WorkerEventState::COMPLETE->value,
            ],
        ]);

        if (0 === $eventCount) {
            return EventDeliveryState::AWAITING;
        }

        return $finishedEventCount === $eventCount ? EventDeliveryState::COMPLETE : EventDeliveryState::RUNNING;
    }

    /**
     * @param array<EventDeliveryState> ...$states
     */
    public function is(...$states): bool
    {
        return in_array($this->get(), $states);
    }
}
