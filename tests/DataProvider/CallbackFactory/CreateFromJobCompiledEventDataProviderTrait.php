<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\JobCompiledEvent;
use App\Tests\Mock\Entity\MockCallback;

trait CreateFromJobCompiledEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobCompiledEventDataProvider(): array
    {
        return [
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_JOB_COMPILED)
                    ->withGetPayloadCall([])
                    ->getMock(),
            ],
        ];
    }
}
