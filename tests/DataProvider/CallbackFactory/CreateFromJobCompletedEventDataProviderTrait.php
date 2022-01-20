<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\JobCompletedEvent;
use App\Tests\Mock\Entity\MockCallback;

trait CreateFromJobCompletedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobCompletedEventDataProvider(): array
    {
        return [
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_JOB_COMPLETED)
                    ->withGetPayloadCall([])
                    ->getMock(),
            ],
        ];
    }
}
