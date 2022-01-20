<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\ExecutionStartedEvent;
use App\Tests\Mock\Entity\MockCallback;

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
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_EXECUTION_STARTED)
                    ->withGetPayloadCall([])
                    ->getMock(),
            ],
        ];
    }
}
