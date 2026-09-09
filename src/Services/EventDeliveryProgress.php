<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\EventDeliveryState;
use App\Repository\WorkerEventRepository;

class EventDeliveryProgress
{
    public function __construct(
        private readonly WorkerEventRepository $repository
    ) {}

    public function get(): EventDeliveryState
    {
        $eventCount = $this->repository->count([]);
        if (0 === $eventCount) {
            return EventDeliveryState::AWAITING;
        }

        $finishedEventCount = $this->repository->countFinished();

        return $finishedEventCount === $eventCount ? EventDeliveryState::COMPLETE : EventDeliveryState::RUNNING;
    }
}
