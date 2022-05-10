<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\JobFailedEvent;

trait CreateFromJobFailedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobFailedEventDataProvider(): array
    {
        return [
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_FAILED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
