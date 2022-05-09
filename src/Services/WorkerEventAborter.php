<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Repository\WorkerEventRepository;

class WorkerEventAborter
{
    public function __construct(
        private WorkerEventRepository $repository,
        private WorkerEventStateMutator $workerEventStateMutator,
    ) {
    }

    public function abort(int $id): void
    {
        $workerEvent = $this->repository->find($id);
        if ($workerEvent instanceof WorkerEvent) {
            $this->workerEventStateMutator->setFailed($workerEvent);
        }
    }
}
