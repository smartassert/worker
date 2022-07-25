<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;

class WorkerEventSerializer
{
    /**
     * @return array<mixed>
     */
    public function serialize(Job $job, WorkerEvent $workerEvent): array
    {
        return array_merge(
            [
                'job' => $job->getLabel(),
            ],
            $workerEvent->toArray()
        );
    }
}
