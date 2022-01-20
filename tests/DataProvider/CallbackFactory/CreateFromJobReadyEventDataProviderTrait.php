<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\JobReadyEvent;
use App\Tests\Mock\Entity\MockCallback;

trait CreateFromJobReadyEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobReadyEventDataProvider(): array
    {
        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_JOB_STARTED)
                    ->withGetPayloadCall([])
                    ->getMock(),
            ],
        ];
    }
}
