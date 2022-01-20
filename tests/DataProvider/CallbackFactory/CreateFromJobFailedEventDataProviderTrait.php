<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\JobFailedEvent;
use App\Tests\Mock\Entity\MockCallback;

trait CreateFromJobFailedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobFailedEventDataProvider(): array
    {
        return [
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_JOB_FAILED)
                    ->withGetPayloadCall([])
                    ->getMock(),
            ],
        ];
    }
}
