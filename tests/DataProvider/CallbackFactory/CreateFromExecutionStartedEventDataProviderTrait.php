<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

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
                'expectedCallback' => WorkerEvent::create(
                    WorkerEvent::TYPE_EXECUTION_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
