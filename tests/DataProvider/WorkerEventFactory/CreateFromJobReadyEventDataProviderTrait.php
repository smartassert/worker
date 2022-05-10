<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\JobReadyEvent;

trait CreateFromJobReadyEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobReadyEventDataProvider(): array
    {
        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
