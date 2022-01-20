<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\JobTimeoutEvent;
use App\Tests\Mock\Entity\MockCallback;

trait CreateFromJobTimeoutEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobTimeoutEventDataProvider(): array
    {
        return [
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(150),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_JOB_TIME_OUT)
                    ->withGetPayloadCall([
                        'maximum_duration_in_seconds' => 150,
                    ])
                    ->getMock(),
            ],
        ];
    }
}
