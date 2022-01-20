<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\SourceCompilation\StartedEvent;
use App\Tests\Mock\Entity\MockCallback;

trait CreateFromCompilationStartedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromCompilationStartedEventDataProvider(): array
    {
        return [
            StartedEvent::class => [
                'event' => new StartedEvent('/app/source/test.yml'),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_COMPILATION_STARTED)
                    ->withGetPayloadCall([
                        'source' => '/app/source/test.yml',
                    ])
                    ->getMock(),
            ],
        ];
    }
}
