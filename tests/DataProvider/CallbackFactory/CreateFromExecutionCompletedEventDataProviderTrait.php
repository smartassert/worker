<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
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
                'expectedCallback' => CallbackEntity::create(
                    CallbackEntity::TYPE_EXECUTION_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
