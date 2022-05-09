<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\WorkerEvent;
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
                'expectedCallback' => WorkerEvent::create(
                    WorkerEvent::TYPE_EXECUTION_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
