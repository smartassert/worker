<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Event\ExecutionStartedEvent;

trait CreateFromExecutionStartedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromExecutionStartedEventDataProvider(): array
    {
        return [
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEvent::TYPE_EXECUTION_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
