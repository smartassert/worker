<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Event\JobCompletedEvent;

trait CreateFromJobCompletedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobCompletedEventDataProvider(): array
    {
        return [
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEvent::TYPE_JOB_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
