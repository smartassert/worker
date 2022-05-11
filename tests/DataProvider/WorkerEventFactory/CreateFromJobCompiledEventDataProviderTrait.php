<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\JobCompiledEvent;

trait CreateFromJobCompiledEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobCompiledEventDataProvider(): array
    {
        return [
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_COMPILED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
