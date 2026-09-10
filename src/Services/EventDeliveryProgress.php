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
    ) {}

    public function get(): EventDeliveryState
    {
        $eventCount = $this->repository->count([]);
        if (0 === $eventCount) {
            return EventDeliveryState::AWAITING;
        }

        $finishedEventCount = $this->repository->countFinished();

        $finishedJobEndedCount = $this->repository->count([
            'type' => 'job/ended',
            'state' => WorkerEventState::FINISHED_STATES,
        ]);

        return $finishedEventCount === $eventCount && $finishedJobEndedCount > 0
            ? EventDeliveryState::COMPLETE
            : EventDeliveryState::RUNNING;
    }
}
