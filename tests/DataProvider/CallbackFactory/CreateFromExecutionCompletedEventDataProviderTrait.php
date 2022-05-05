<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
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
                    CallbackInterface::TYPE_EXECUTION_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
