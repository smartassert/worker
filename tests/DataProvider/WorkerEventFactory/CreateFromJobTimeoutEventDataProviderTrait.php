<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\JobTimeoutEvent;

trait CreateFromJobTimeoutEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobTimeoutEventDataProvider(): array
    {
        return [
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(150),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_TIME_OUT,
                    '{{ job_label }}',
                    [
                        'maximum_duration_in_seconds' => 150,
                    ]
                ),
            ],
        ];
    }
}
