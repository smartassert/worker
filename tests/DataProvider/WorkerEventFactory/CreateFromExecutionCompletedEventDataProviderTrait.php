<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\ExecutionCompletedEvent;

trait CreateFromExecutionCompletedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromExecutionCompletedEventDataProvider(): array
    {
        return [
            ExecutionCompletedEvent::class => [
                'event' => new ExecutionCompletedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::EXECUTION_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
