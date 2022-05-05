<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
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
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_EXECUTION_STARTED, []),
            ],
        ];
    }
}
