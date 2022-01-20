<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\ExecutionCompletedEvent;
use App\Tests\Mock\Entity\MockCallback;

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
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_EXECUTION_COMPLETED)
                    ->withGetPayloadCall([])
                    ->getMock(),
            ],
        ];
    }
}
